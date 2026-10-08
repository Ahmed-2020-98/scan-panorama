<?php

namespace App\Models;

use App\Enums\CaseFileType;
use App\Enums\CaseStatus;
use App\Enums\WorkflowStatus;
use App\Support\AccessScope;
use App\Support\CodeGenerator;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Database\Factories\MedicalCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $case_code
 * @property int $patient_id
 * @property int $branch_id
 * @property CarbonInterface $exam_date
 * @property string|null $notes_internal
 * @property string|null $notes_for_doctor
 * @property string $share_token
 * @property string|null $patient_share_token
 * @property CarbonInterface|null $share_expires_at
 * @property CarbonInterface|null $share_revoked_at
 * @property CarbonInterface|null $shared_at
 * @property CarbonInterface|null $first_opened_at
 * @property CarbonInterface|null $last_opened_at
 * @property int $open_count
 * @property int|null $created_by
 * @property CarbonInterface|null $archived_at
 * @property CarbonInterface|null $created_at
 * @property-read Branch $branch
 * @property-read CaseStatus $status
 * @property int|null $doctor_id
 * @property int|null $exam_type_id
 * @property Patient|null $patient
 * @property Doctor|null $doctor
 * @property ExamType|null $examType
 * @property WorkflowStatus $workflow_status
 * @property int|null $technician_id
 * @property string|null $medical_notes
 * @property string|null $technical_notes
 * @property int|null $base_price_minor
 * @property int|null $discount_minor
 * @property int|null $final_price_minor
 * @property string|null $currency
 * @property int|null $discount_by
 * @property string|null $deletion_batch_id
 */
#[Fillable([
    'case_code', 'patient_id', 'doctor_id', 'branch_id', 'exam_type_id', 'exam_date',
    'notes_internal', 'notes_for_doctor', 'created_by', 'technician_id', 'workflow_status', 'medical_notes', 'technical_notes', 'base_price_minor', 'discount_minor', 'final_price_minor', 'discount_reason', 'discount_by', 'discount_at', 'currency', 'deletion_batch_id',
])]
class MedicalCase extends Model
{
    protected $attributes = ['workflow_status' => 'new'];

    /** @use HasFactory<MedicalCaseFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope('access', function (Builder $query) {
            if (auth()->check()) {
                AccessScope::applyCases($query, auth()->user());
            }
        });
        static::created(function (MedicalCase $case) {
            Patient::withoutGlobalScope('access')->findOrFail($case->patient_id)->branches()->syncWithoutDetaching([$case->branch_id]);
        });
        static::creating(function (MedicalCase $case) {
            $case->case_code ??= CodeGenerator::caseCode();
            $case->share_token ??= self::newShareToken();
            $case->share_expires_at ??= self::defaultShareExpiry();
        });
    }

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'workflow_status' => WorkflowStatus::class,
            'discount_at' => 'datetime',
            'base_price_minor' => 'integer',
            'final_price_minor' => 'integer',
            'discount_minor' => 'integer',
            'share_expires_at' => 'datetime',
            'share_revoked_at' => 'datetime',
            'shared_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'archived_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ExamType, $this>
     */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CaseFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(CaseFile::class)->latest('id');
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    public function getStatusAttribute(): CaseStatus
    {
        if ($this->archived_at) {
            return CaseStatus::Archived;
        }

        $filesCount = $this->files_count ?? ($this->relationLoaded('files') ? $this->files->where('storage_status', 'ready')->count() : $this->files()->where('storage_status', 'ready')->count());

        return match (true) {
            $filesCount === 0 => CaseStatus::Incomplete,
            $this->first_opened_at !== null => CaseStatus::Opened,
            $this->shared_at !== null => CaseStatus::Shared,
            default => CaseStatus::Ready,
        };
    }

    /**
     * Eager load per-type file counts used by the tables.
     *
     * @param  Builder<MedicalCase>  $query
     */
    public function scopeWithFileCounts(Builder $query): void
    {
        $counts = ['files' => fn (Builder $q) => $q->where('storage_status', 'ready')];

        foreach (CaseFileType::cases() as $type) {
            $counts["files as {$type->value}_files_count"] = fn (Builder $q) => $q->where('type', $type->value)->where('storage_status', 'ready');
        }

        $query->withCount($counts);
    }

    public function fileCount(CaseFileType $type): int
    {
        $attribute = "{$type->value}_files_count";

        return (int) ($this->{$attribute} ?? $this->files()->where('type', $type->value)->where('storage_status', 'ready')->count());
    }

    /**
     * @param  Builder<MedicalCase>  $query
     */
    public function scopeWithStatus(Builder $query, ?string $status): void
    {
        match (CaseStatus::tryFrom((string) $status)) {
            CaseStatus::Archived => $query->whereNotNull('archived_at'),
            CaseStatus::Incomplete => $query->whereNull('archived_at')->whereDoesntHave('files', fn ($q) => $q->where('storage_status', 'ready')),
            CaseStatus::Ready => $query->whereNull('archived_at')->whereHas('files', fn ($q) => $q->where('storage_status', 'ready'))->whereNull('shared_at')->whereNull('first_opened_at'),
            CaseStatus::Shared => $query->whereNull('archived_at')->whereHas('files', fn ($q) => $q->where('storage_status', 'ready'))->whereNotNull('shared_at')->whereNull('first_opened_at'),
            CaseStatus::Opened => $query->whereNull('archived_at')->whereHas('files', fn ($q) => $q->where('storage_status', 'ready'))->whereNotNull('first_opened_at'),
            null => $query->whereNull('archived_at'),
        };
    }

    /**
     * @param  Builder<MedicalCase>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('case_code', 'like', "%{$term}%")
                ->orWhereHas('patient', fn (Builder $p) => $p->withTrashed()
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('file_number', 'like', "%{$term}%"));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Sharing
    |--------------------------------------------------------------------------
    */

    public static function newShareToken(): string
    {
        return Str::random(48);
    }

    public static function defaultShareExpiry(): ?CarbonInterface
    {
        $days = (int) Setting::get('share_link_days');

        return $days > 0 ? now()->addDays($days) : null;
    }

    public function isShareActive(): bool
    {
        return ! $this->trashed() && $this->patient !== null && ! $this->patient->trashed() && $this->share_revoked_at === null
            && ($this->share_expires_at === null || $this->share_expires_at->isFuture());
    }

    public function shareUrl(): string
    {
        return route('shared.show', $this->share_token);
    }

    public function regenerateShareLink(): void
    {
        $this->forceFill([
            'share_token' => self::newShareToken(),
            'patient_share_token' => null,
            'share_revoked_at' => null,
            'share_expires_at' => self::defaultShareExpiry(),
        ])->save();
    }

    public function revokeShareLink(): void
    {
        $this->forceFill(['share_revoked_at' => now()])->save();
    }

    public function markShared(): void
    {
        if ($this->shared_at === null) {
            $this->forceFill(['shared_at' => now()])->save();
        }
    }

    public function whatsappMessage(): string
    {
        $lines = [
            'مرحبًا '.($this->doctor->display_name ?? 'دكتور').'،',
            'ملفات أشعة المريض: '.$this->patient->name,
            'الفحص: '.($this->examType->name ?? 'لم يحدد بعد'),
            'كود الحالة: '.$this->case_code,
            '',
            'لعرض وتحميل الملفات:',
            $this->shareUrl(),
        ];

        return implode("\n", $lines);
    }

    public function whatsappUrl(): string
    {
        return Phone::whatsappUrl($this->doctor?->whatsappNumber(), $this->whatsappMessage());
    }

    /**
     * Separate link for the patient: same expiry/revocation as the doctor link,
     * but its own token and a view without the doctor-facing notes.
     */
    public function patientShareUrl(): string
    {
        if (! $this->patient_share_token) {
            $this->forceFill(['patient_share_token' => self::newShareToken()])->save();
        }

        return route('patient-share.show', $this->patient_share_token);
    }

    public function patientWhatsappMessage(): string
    {
        $center = (string) Setting::get('center_name');
        $phone = Setting::get('contact_phone');
        $lines = array_filter([
            $this->patient->identity_incomplete ? 'مرحبًا،' : 'مرحبًا '.$this->patient->name.'،',
            'نتائج أشعتك من '.$center.' جاهزة.',
            'الفحص: '.($this->examType->name ?? 'أشعة'),
            'تاريخ الفحص: '.$this->exam_date->format('d/m/Y'),
            '',
            'لعرض وتحميل الملفات:',
            $this->patientShareUrl(),
            $phone ? "\nللاستفسار: ".$phone : null,
        ], fn ($line) => $line !== null);

        return implode("\n", $lines);
    }

    public function patientWhatsappUrl(): ?string
    {
        return Phone::isMobile($this->patient?->phone) ? Phone::whatsappUrl($this->patient->phone, $this->patientWhatsappMessage()) : null;
    }

    /**
     * Track that the doctor opened the case (via portal or share link).
     */
    public function recordOpen(): void
    {
        $now = now();

        static::withoutTimestamps(fn () => $this->forceFill([
            'first_opened_at' => $this->first_opened_at ?? $now,
            'last_opened_at' => $now,
            'open_count' => $this->open_count + 1,
        ])->save());
    }
}
