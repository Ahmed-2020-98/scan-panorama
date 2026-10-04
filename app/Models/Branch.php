<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property bool $is_active
 */
#[Fillable(['name', 'address', 'phone', 'is_active'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Patient, $this> */
    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class);
    }

    /** @return BelongsToMany<ExamType, $this> */
    public function examTypes(): BelongsToMany
    {
        return $this->belongsToMany(ExamType::class)->withPivot('base_price_minor', 'is_active');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsToMany<Doctor, $this> */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class);
    }

    /**
     * @return HasMany<MedicalCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(MedicalCase::class);
    }

    /**
     * @param  Builder<Branch>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
