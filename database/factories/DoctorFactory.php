<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->doctor(),
            'code' => fake()->unique()->bothify('??###'),
            'title' => fake()->randomElement(['د.', 'أ.د.']),
            'specialty' => fake()->randomElement(['تقويم الأسنان', 'علاج الجذور', 'زراعة الأسنان', 'جراحة الفم', 'طب أسنان عام']),
        ];
    }
}
