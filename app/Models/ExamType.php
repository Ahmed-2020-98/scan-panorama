<?php

namespace App\Models;

use Database\Factories\ExamTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property int $sort
 * @property bool $is_active
 * @property int|null $base_price_minor
 * @property string|null $description
 */
#[Fillable(['name', 'category', 'sort', 'is_active', 'base_price_minor', 'description'])]
class ExamType extends Model
{
    /** @use HasFactory<ExamTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer', 'base_price_minor' => 'integer'];
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('base_price_minor', 'is_active');
    }

    /** @return HasMany<MedicalCase, $this> */
    public function cases(): HasMany
    {
        return $this->hasMany(MedicalCase::class);
    }

    /**
     * @param  Builder<ExamType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<ExamType>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }
}
