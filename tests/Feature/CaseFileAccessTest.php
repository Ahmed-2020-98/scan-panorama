<?php

namespace Tests\Feature;

use App\Enums\CaseFileType;
use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaseFileAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function fileFor(MedicalCase $case, CaseFileType $type = CaseFileType::Report, string $mime = 'image/png', string $name = 'scan.png'): CaseFile
    {
        $path = "cases/{$case->id}/{$type->value}/file-".uniqid().'.bin';
        Storage::disk('local')->put($path, 'contents');

        return CaseFile::factory()->create([
            'medical_case_id' => $case->id,
            'type' => $type,
            'original_name' => $name,
            'mime' => $mime,
            'path' => $path,
            'size' => 8,
        ]);
    }

    public function test_doctor_can_view_their_own_files_and_the_access_is_recorded(): void
    {
        $case = MedicalCase::factory()->create();
        $file = $this->fileFor($case);

        $case->doctor->branches()->syncWithoutDetaching([$case->branch_id]);
        $this->actingAs($case->doctor->user)
            ->get(route('files.show', [$file, 'view']))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertNotNull($case->refresh()->first_opened_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'file.viewed', 'subject_id' => $file->id]);
    }

    public function test_doctor_can_not_access_another_doctors_file(): void
    {
        $file = $this->fileFor(MedicalCase::factory()->create());
        $otherDoctor = MedicalCase::factory()->create()->doctor;

        $this->actingAs($otherDoctor->user)
            ->get(route('files.show', [$file, 'download']))
            ->assertForbidden();
    }

    public function test_staff_downloads_are_logged_without_marking_the_case_opened(): void
    {
        $case = MedicalCase::factory()->create();
        $file = $this->fileFor($case, CaseFileType::Dicom, 'application/zip', 'scan.zip');

        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]))
            ->get(route('files.show', [$file, 'download']))
            ->assertOk()
            ->assertDownload('scan.zip');

        $this->assertNull($case->refresh()->first_opened_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'file.downloaded', 'subject_id' => $file->id]);
    }

    public function test_guests_can_not_access_files_without_a_share_link(): void
    {
        $file = $this->fileFor(MedicalCase::factory()->create());

        $this->get(route('files.show', [$file, 'view']))->assertRedirect(route('login'));
    }
}
