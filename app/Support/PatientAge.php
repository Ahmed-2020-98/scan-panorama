<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PatientAge
{
    public static function yearFromAge(int $age, int $currentYear): int
    {
        if ($age < 0 || $age > 130) {
            throw ValidationException::withMessages(['age' => 'العمر بين 0 و130 سنة.']);
        }

        return $currentYear - $age;
    }

    public static function ageFromYear(int $year, int $currentYear): int
    {
        self::yearFromAge($currentYear - $year, $currentYear);

        return $currentYear - $year;
    }
}
