<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalListTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_sees_all_cases_on_one_page_without_pagination(): void
    {
        $doctor = Doctor::factory()->create();
        $branch = Branch::factory()->create();
        $doctor->branches()->attach($branch);
        $patient = Patient::factory()->create();
        $cases = MedicalCase::factory()->count(30)->create(['doctor_id' => $doctor->id, 'branch_id' => $branch->id, 'patient_id' => $patient->id]);
        $this->actingAs($doctor->user);

        $response = $this->get(route('portal'))->assertOk()->assertSee('30');
        $cases->each(fn ($case) => $response->assertSee($case->case_code));
        $response->assertDontSee('?page=2', false);
    }
}
