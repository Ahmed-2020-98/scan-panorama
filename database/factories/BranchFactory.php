<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['المعادي', 'شبرا', 'مدينة نصر', 'الدقي', 'الشيخ زايد', 'التجمع الخامس']),
            'address' => fake()->streetAddress(),
            'phone' => '02'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }
}
