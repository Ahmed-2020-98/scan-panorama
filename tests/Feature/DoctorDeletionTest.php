<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\User;
use App\Services\RecordRecovery;
use App\Support\AccessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DoctorDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_doctor_keeps_cases_disables_login_and_restores(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $case = MedicalCase::factory()->create();
        $doctor = $case->doctor;
        $name = $doctor->display_name;

        Livewire::test('pages::admin.doctors.index')->call('delete', $doctor->id)->assertHasNoErrors();

        $this->assertSoftDeleted($doctor);
        $this->assertFalse($doctor->user->fresh()->is_active);
        $this->assertNotSoftDeleted($case);
        $this->assertSame($name, $case->fresh()->doctor->display_name);
        $this->assertFalse(AccessScope::doctors($admin)->whereKey($doctor->id)->exists());
        $this->get(route('cases.show', $case))->assertOk()->assertSee($name);
        $this->get(route('doctors.index'))->assertOk()->assertDontSee($doctor->code);

        $this->get(route('recycle-bin'))->assertOk()->assertSee($name);
        Livewire::test('pages::admin.recycle-bin')->call('restore', 'doctor', $doctor->id);

        $this->assertNotSoftDeleted($doctor->fresh());
        $this->assertTrue($doctor->user->fresh()->is_active);
    }

    public function test_restore_puts_back_a_previously_disabled_account_as_it_was(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $doctor = Doctor::factory()->create();
        $doctor->user->update(['is_active' => false]);
        $doctor->branches()->attach(Branch::factory()->create());

        Livewire::test('pages::admin.doctors.index')->call('delete', $doctor->id);
        Livewire::test('pages::admin.recycle-bin')->call('restore', 'doctor', $doctor->id);

        $doctor = $doctor->fresh();
        $this->assertFalse($doctor->user->is_active);
        $this->assertSame(1, $doctor->branches()->count());
        $this->assertNull($doctor->user_was_active);
    }

    public function test_restore_window_is_thirty_days_then_unreferenced_doctors_are_purged(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $withCases = MedicalCase::factory()->create()->doctor;
        $unused = Doctor::factory()->create();
        $unusedUser = $unused->user_id;

        Livewire::test('pages::admin.doctors.index')->call('delete', $withCases->id)->call('delete', $unused->id);

        $this->travel(29)->days();
        $this->get(route('recycle-bin'))->assertOk()->assertSee($unused->display_name)->assertSee('متبقي');

        $this->travel(2)->days();
        $this->get(route('recycle-bin'))->assertOk()->assertDontSee($withCases->display_name);
        $this->assertNull(Doctor::withTrashed()->find($unused->id), 'unreferenced doctor is purged');
        $this->assertNull(User::find($unusedUser));
        $this->assertNotNull(Doctor::withTrashed()->find($withCases->id), 'doctor with cases stays archived');

        try {
            RecordRecovery::restore(auth()->user(), Doctor::withTrashed()->findOrFail($withCases->id));
            $this->fail('restore after the window must be refused');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_deleted_doctor_cannot_log_in(): void
    {
        $doctor = Doctor::factory()->create();
        $doctor->user->update(['phone' => '01055555555']);
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test('pages::admin.doctors.index')->call('delete', $doctor->id);
        auth()->logout();

        $this->post(route('login.store'), ['email' => '01055555555', 'password' => 'password']);
        $this->assertGuest();
    }
}
