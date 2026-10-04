<?php

namespace App\Models;

use App\Enums\CaseFileType;
use Database\Factories\CaseFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $medical_case_id
 * @property CaseFileType $type
 * @property string $original_name
 * @property string $path
 * @property string|null $mime
 * @property int $size
 * @property string|null $sha256
 * @property int|null $uploaded_by
 * @property-read MedicalCase|null $medicalCase
 * @property-read User|null $uploader
 */
#[Fillable(['medical_case_id', 'type', 'original_name', 'path', 'mime', 'size', 'sha256', 'uploaded_by', 'disk', 'provider_id', 'storage_status', 'storage_error', 'staging_path', 'is_shared', 'deletion_batch_id'])]
class CaseFile extends Model
{
    /** @use HasFactory<CaseFileFactory> */
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected static function booted(): void
    {
        // Soft-deleted assets retain their bytes for Manager recovery.
    }

    protected function casts(): array
    {
        return [
            'type' => CaseFileType::class,
            'size' => 'integer',
            'is_shared' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MedicalCase, $this>
     */
    public function medicalCase(): BelongsTo
    {
        return $this->belongsTo(MedicalCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    /**
     * Images and PDFs open in the browser; everything else downloads.
     */
    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime, 'video/');
    }

    public function isViewable(): bool
    {
        return $this->isImage() || $this->isPdf() || $this->isVideo();
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
