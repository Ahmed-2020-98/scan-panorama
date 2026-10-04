<?php

namespace Database\Factories;

use App\Models\ExamType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamType>
 */
class ExamTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => '2D - '.fake()->unique()->word(),
            'category' => '2D',
            'sort' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
