<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalCase>
 */
class MedicalCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'branch_id' => Branch::factory(),
            'exam_type_id' => ExamType::factory(),
            'exam_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
