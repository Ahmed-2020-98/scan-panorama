<?php

namespace Database\Factories;

use App\Enums\CaseFileType;
use App\Models\CaseFile;
use App\Models\MedicalCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseFile>
 */
class CaseFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'medical_case_id' => MedicalCase::factory(),
            'type' => CaseFileType::Report,
            'original_name' => 'panorama.png',
            'path' => 'cases/test/'.Str::uuid().'.png',
            'mime' => 'image/png',
            'size' => 1024,
        ];
    }
}
