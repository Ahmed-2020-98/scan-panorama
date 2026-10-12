<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property string $category
 * @property int $amount_minor
 * @property string $currency
 * @property CarbonInterface $spent_on
 * @property string|null $note
 * @property int|null $user_id
 * @property int|null $shifts
 * @property int|null $created_by
 * @property-read Branch|null $branch
 * @property-read User|null $user
 * @property-read User|null $creator
 */
#[Fillable(['branch_id', 'category', 'amount_minor', 'currency', 'spent_on', 'note', 'user_id', 'shifts', 'created_by'])]
class Expense extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'salaries' => 'رواتب',
        'rent' => 'إيجار',
        'utilities' => 'كهرباء ومياه وإنترنت',
        'supplies' => 'خامات ومستلزمات',
        'maintenance' => 'صيانة أجهزة',
        'marketing' => 'دعاية وتسويق',
        'other' => 'أخرى',
    ];

    protected function casts(): array
    {
        return ['spent_on' => 'date', 'amount_minor' => 'integer', 'shifts' => 'integer'];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
