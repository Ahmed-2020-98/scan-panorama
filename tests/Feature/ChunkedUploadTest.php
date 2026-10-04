<?php

namespace Tests\Feature;

use App\Models\MedicalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['radiology.upload_provider' => 'local']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sendChunk(MedicalCase $case, string $chunk, int $index, int $total, string $name, int $size, string $type = 'dicom', string $uploadId = 'upload-12345678', array $overrides = []): TestResponse
    {
        return $this->postJson(route('cases.uploads.store', $case), array_merge([
            'type' => $type,
            'upload_id' => $uploadId,
            'chunk_index' => $index,
            'total_chunks' => $total,
            'file_name' => $name,
            'file_size' => $size,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ], $overrides));
    }

    public function test_file_is_assembled_from_chunks_and_attached_to_the_case(): void
    {
        $case = MedicalCase::factory()->create();
        $this->actingAs($user = User::factory()->reception()->create(['branch_id' => $case->branch_id]));

        $contents = random_bytes(3000);
        $parts = str_split($contents, 1024);

        foreach ($parts as $index => $part) {
            $response = $this->sendChunk($case, $part, $index, count($parts), 'CBCT Scan.zip', strlen($contents))->assertOk();
        }

        $response->assertJson(['done' => true]);

        $file = $case->files()->sole();
        $this->assertSame('dicom', $file->type->value);
        $this->assertSame('CBCT Scan.zip', $file->original_name);
        $this->assertSame(3000, $file->size);
        $this->assertSame(hash('sha256', $contents), $file->sha256);
        $this->assertSame($user->id, $file->uploaded_by);
        $this->assertSame($contents, Storage::disk('local')->get($file->path));
        $this->assertSame([], Storage::disk('local')->directories('chunks'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'file.uploaded', 'subject_id' => $file->id]);
    }

    public function test_intermediate_chunks_report_progress(): void
    {
        $case = MedicalCase::factory()->create();
        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]));

        $this->sendChunk($case, 'abc', 0, 2, 'scan.zip', 6)->assertOk()->assertJson(['done' => false, 'received' => 1]);
        $this->assertSame(0, $case->files()->count());
    }

    public function test_disallowed_extensions_are_rejected(): void
    {
        $case = MedicalCase::factory()->create();
        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]));

        $this->sendChunk($case, 'MZ', 0, 1, 'virus.exe', 2)->assertStatus(422)->assertJsonValidationErrors('chunk');
    }

    public function test_documents_must_really_be_images_or_pdfs(): void
    {
        $case = MedicalCase::factory()->create();
        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]));

        $this->sendChunk($case, 'this is not a png', 0, 1, 'fake.png', 17, 'report')
            ->assertStatus(422)
            ->assertJsonValidationErrors('chunk');

        $this->assertSame(0, $case->files()->count());
    }

    public function test_oversized_files_are_rejected_before_upload(): void
    {
        config(['radiology.max_upload_mb.dicom' => 1]);
        $case = MedicalCase::factory()->create();
        $this->actingAs(User::factory()->reception()->create(['branch_id' => $case->branch_id]));

        $this->sendChunk($case, 'x', 0, 3, 'big.zip', 2 * 1024 * 1024)->assertStatus(422);
    }

    public function test_doctors_and_archived_cases_can_not_receive_uploads(): void
    {
        $case = MedicalCase::factory()->create();

        $case->doctor->branches()->syncWithoutDetaching([$case->branch_id]);
        $this->actingAs($case->doctor->user);
        $this->sendChunk($case, 'x', 0, 1, 'scan.zip', 1)->assertForbidden();

        $case->forceFill(['archived_at' => now()])->save();
        $this->actingAs(User::factory()->admin()->create());
        $this->sendChunk($case, 'x', 0, 1, 'scan.zip', 1)->assertForbidden();
    }
}
