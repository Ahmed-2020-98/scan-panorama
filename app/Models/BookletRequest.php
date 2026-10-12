<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request for a new referral booklet (دفتر تحويل) for a doctor, raised by
 * staff or by the doctor from their private link.
 *
 * @property int $id
 * @property int $doctor_id
 * @property int|null $branch_id
 * @property int $quantity
 * @property string $source
 * @property string|null $note
 * @property string $status
 * @property int|null $requested_by
 * @property CarbonInterface|null $delivered_at
 * @property int|null $delivered_by
 * @property CarbonInterface $created_at
 * @property-read Doctor|null $doctor
 * @property-read Branch|null $branch
 * @property-read User|null $requester
 */
#[Fillable(['doctor_id', 'branch_id', 'quantity', 'source', 'note', 'status', 'requested_by', 'delivered_at', 'delivered_by'])]
class BookletRequest extends Model
{
    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'quantity' => 'integer'];
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class)->withTrashed();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }
}
