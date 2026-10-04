<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'name' => fake()->firstName($gender).' '.fake()->firstNameMale().' '.fake()->lastName(),
            'phone' => '01'.fake()->randomElement(['0', '1', '2', '5']).fake()->numerify('########'),
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-70 years', '-6 years'),
        ];
    }
}
