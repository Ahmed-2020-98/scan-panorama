<?php

namespace App\Models;

use App\Support\AccessScope;
use App\Support\CodeGenerator;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $file_number
 * @property string $name
 * @property string|null $phone
 * @property string|null $gender
 * @property Carbon|null $birth_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read int|null $age
 * @property bool $birth_year_only
 * @property bool $identity_incomplete
 * @property string|null $deletion_batch_id
 */
#[Fillable(['file_number', 'name', 'phone', 'gender', 'birth_date', 'notes', 'created_by', 'birth_year_only', 'identity_incomplete', 'deletion_batch_id'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    public const GENDERS = ['male' => 'ذكر', 'female' => 'أنثى'];

    protected static function booted(): void
    {
        static::addGlobalScope('access', function (Builder $query) {
            if (auth()->check() && ! auth()->user()->isAdmin()) {
                $query->whereIn('patients.id', AccessScope::patients(auth()->user())->select('patients.id'));
            }
        });
        static::creating(function (Patient $patient) {
            $patient->file_number ??= CodeGenerator::patientFileNumber();
        });
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'birth_year_only' => 'boolean', 'identity_incomplete' => 'boolean'];
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /** @return HasMany<MedicalCase, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(MedicalCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? ($this->birth_year_only ? now()->year - $this->birth_date->year : (int) $this->birth_date->diffInYears(now())) : null;
    }

    public function genderLabel(): ?string
    {
        return self::GENDERS[$this->gender] ?? null;
    }

    /**
     * @param  Builder<Patient>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('file_number', 'like', "%{$term}%");
        });
    }
}
