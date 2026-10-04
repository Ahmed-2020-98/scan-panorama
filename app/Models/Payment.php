<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $medical_case_id
 * @property int $branch_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $method
 * @property int $collected_by
 * @property int|null $adjustment_of_id
 * @property string|null $reason
 * @property CarbonInterface $received_at
 */
#[Fillable(['medical_case_id', 'branch_id', 'amount_minor', 'currency', 'method', 'collected_by', 'received_at', 'request_id', 'adjustment_of_id', 'reason'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'received_at' => 'datetime'];
    }

    /** @return BelongsTo<MedicalCase, $this> */
    public function medicalCase(): BelongsTo
    {
        return $this->belongsTo(MedicalCase::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
