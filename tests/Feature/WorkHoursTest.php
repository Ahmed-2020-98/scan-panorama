<?php

namespace Tests\Feature;

use App\Models\MedicalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WorkHoursTest extends TestCase
{
    use RefreshDatabase;

    /** Monday 2026-10-26 and Thursday 2026-10-29, 09:00–17:00 Cairo time. */
    private function technician(array $extra = []): User
    {
        return User::factory()->create(['role' => 'technician', 'password' => 'Password-123!', 'work_days' => [1, 4], 'work_from' => '09:00', 'work_until' => '17:00'] + $extra);
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time, 'Africa/Cairo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_schedule_is_checked_by_day_and_hour(): void
    {
        $tech = $this->technician();

        $this->assertTrue($tech->canWorkNow(Carbon::parse('2026-10-26 09:00', 'Africa/Cairo')));
        $this->assertTrue($tech->canWorkNow(Carbon::parse('2026-10-29 16:59', 'Africa/Cairo')));
        $this->assertFalse($tech->canWorkNow(Carbon::parse('2026-10-26 17:00', 'Africa/Cairo')), 'the end hour is closed');
        $this->assertFalse($tech->canWorkNow(Carbon::parse('2026-10-27 10:00', 'Africa/Cairo')), 'Tuesday is not his day');
        $this->assertFalse($tech->canWorkNow(Carbon::parse('2026-10-26 08:30', 'Africa/Cairo')));

        $this->assertTrue(User::factory()->admin()->create(['work_days' => [1]])->canWorkNow(Carbon::parse('2026-10-27 10:00', 'Africa/Cairo')), 'managers are never locked');
        $this->assertTrue(User::factory()->create(['role' => 'technician'])->canWorkNow(), 'no schedule means always open');
    }

    public function test_login_is_refused_outside_the_schedule_with_the_schedule_in_the_message(): void
    {
        $tech = $this->technician();

        $this->at('2026-10-27 10:00');
        $this->post(route('login.store'), ['email' => $tech->email, 'password' => 'Password-123!'])
            ->assertSessionHasErrors(['email' => 'حسابك مفتوح الإثنين والخميس من 9:00 ص لـ 5:00 م بس.']);
        $this->assertGuest();

        $this->at('2026-10-26 10:00');
        $this->post(route('login.store'), ['email' => $tech->email, 'password' => 'Password-123!'])->assertRedirect();
        $this->assertAuthenticatedAs($tech);
    }

    public function test_signed_in_user_is_signed_out_on_the_next_request_once_hours_end(): void
    {
        $tech = $this->technician();
        $this->at('2026-10-26 16:00');
        $this->actingAs($tech)->get(route('technician'))->assertOk();

        $this->at('2026-10-26 17:05');
        $this->get(route('technician'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_open_today_lets_the_user_in_until_the_end_of_the_day(): void
    {
        $tech = $this->technician();
        $manager = User::factory()->admin()->create();
        $this->at('2026-10-27 10:00');

        Livewire::actingAs($manager)->test('pages::admin.users')->call('toggleOpenToday', $tech->id)->assertSee('مفتوح النهارده استثنائي');
        $this->assertTrue($tech->fresh()->canWorkNow());

        $this->at('2026-10-28 08:00');
        $this->assertFalse($tech->fresh()->canWorkNow(), 'the exception ends with the day');
    }

    public function test_upload_already_running_finishes_but_a_new_one_is_refused(): void
    {
        Storage::fake('local');
        config(['radiology.upload_provider' => 'local']);
        $case = MedicalCase::factory()->create();
        $tech = $this->technician(['branch_id' => $case->branch_id]);
        $tech->branches()->sync([$case->branch_id]);
        $case->update(['technician_id' => $tech->id]);
        $chunk = fn (string $id, int $index) => ['type' => 'dicom', 'upload_id' => $id, 'chunk_index' => $index, 'total_chunks' => 2, 'file_name' => 'cbct.zip', 'file_size' => 8, 'chunk' => UploadedFile::fake()->createWithContent('chunk', 'abcd')];

        $this->at('2026-10-26 16:59');
        $this->actingAs($tech)->postJson(route('cases.uploads.store', $case), $chunk('upload-running1', 0))->assertOk();

        $this->at('2026-10-26 17:01');
        $this->get(route('technician'))->assertStatus(423)->assertSee('هتكمل');
        $this->assertAuthenticatedAs($tech);
        $this->postJson(route('cases.uploads.store', $case), $chunk('upload-running1', 1))->assertOk()->assertJson(['done' => true]);

        $this->postJson(route('cases.uploads.store', $case), $chunk('upload-new-0002', 0))->assertStatus(423);
        $this->assertGuest();
    }
}
