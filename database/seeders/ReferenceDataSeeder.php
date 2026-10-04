<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ExamType;
use App\Models\Setting;
use App\Models\ViewerLink;
use Illuminate\Database\Seeder;

/**
 * Data the center needs in production too: branches, exam types,
 * DICOM viewer links and default settings.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['المعادي', 'شبرا'] as $name) {
            Branch::firstOrCreate(['name' => $name]);
        }

        $examTypes = [
            ['2D - panorama', '2D'],
            ['2D - cephalometric', '2D'],
            ['3D CBCT - Both Arches', '3D'],
            ['3D CBCT - Maxilla', '3D'],
            ['3D CBCT - Mandible', '3D'],
            ['3D CBCT - Quadrant', '3D'],
            ['3D CBCT - Segment', '3D'],
            ['3D Endo Mode - Endo mode', '3D'],
        ];

        foreach ($examTypes as $index => [$name, $category]) {
            ExamType::firstOrCreate(['name' => $name], ['category' => $category, 'sort' => $index + 1]);
        }

        if (ViewerLink::query()->doesntExist()) {
            $links = [
                ['Romexis® Viewer', 'windows', 'https://www.planmeca.com/software/'],
                ['OnDemand3D Communicator Viewer', 'windows', 'https://www.ondemand3d.com/'],
                ['Atomica Planner AI', 'windows', 'https://atomica.ai/'],
                ['Implastation', 'windows', 'https://www.implastation.com/'],
                ['IDV for Android', 'mobile', 'https://play.google.com/store/search?q=IDV%20DICOM%20viewer&c=apps'],
                ['IDV for iPhone', 'mobile', 'https://apps.apple.com/search?term=IDV%20DICOM%20viewer'],
            ];

            foreach ($links as $index => [$name, $platform, $url]) {
                ViewerLink::create(['name' => $name, 'platform' => $platform, 'url' => $url, 'sort' => $index + 1]);
            }
        }

        /** @var array<string, string|int|null> $defaults */
        $defaults = config('radiology.defaults', []);
        $existing = Setting::query()->pluck('key')->flip()->all();

        Setting::put(array_diff_key($defaults, $existing));
    }
}
