<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    /**
     * @return list<string>
     */
    private function staffUrls(MedicalCase $case): array
    {
        return [
            route('dashboard'),
            route('cases.index'),
            route('cases.create'),
            route('cases.show', $case),
            route('cases.edit', $case),
            route('patients.index'),
            route('patients.show', $case->patient),
            route('patients.edit', $case->patient),
        ];
    }

    /**
     * @return list<string>
     */
    private function adminUrls(Doctor $doctor): array
    {
        return [
            route('doctors.index'),
            route('doctors.create'),
            route('doctors.edit', $doctor),
            route('branches.index'),
            route('exam-types.index'),
            route('users.index'),
            route('center-settings'),
            route('activity.index'),
        ];
    }

    public function test_admin_can_open_every_back_office_page(): void
    {
        $case = MedicalCase::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        foreach ([...$this->staffUrls($case), ...$this->adminUrls($case->doctor)] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_reception_can_operate_cases_but_not_administer(): void
    {
        $case = MedicalCase::factory()->create();
        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]));

        foreach ($this->staffUrls($case) as $url) {
            $this->get($url)->assertOk();
        }

        foreach ($this->adminUrls($case->doctor) as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_doctor_only_sees_the_portal_and_their_own_cases(): void
    {
        $own = MedicalCase::factory()->create();
        $own->doctor->branches()->syncWithoutDetaching([$own->branch_id]);
        $other = MedicalCase::factory()->create();
        $otherName = $other->patient->name;
        $this->actingAs($own->doctor->user);

        $this->get(route('portal'))
            ->assertOk()
            ->assertSee($own->patient->name)
            ->assertDontSee($otherName);

        $this->get(route('portal.cases.show', $own))->assertOk();
        $this->get(route('portal.cases.show', $other))->assertNotFound();

        foreach ($this->staffUrls($own) as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_opening_a_case_in_the_portal_marks_it_as_opened(): void
    {
        $case = MedicalCase::factory()->create();

        $case->doctor->branches()->syncWithoutDetaching([$case->branch_id]);
        $this->actingAs($case->doctor->user)->get(route('portal.cases.show', $case))->assertOk();

        $case->refresh();
        $this->assertNotNull($case->first_opened_at);
        $this->assertSame(1, $case->open_count);
        $this->assertDatabaseHas('activity_logs', ['action' => 'portal.opened', 'medical_case_id' => $case->id]);
    }
}
