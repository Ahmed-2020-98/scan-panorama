<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable(['doctor_id', 'branch_id', 'responsible_user_id', 'visited_at', 'next_visit_at', 'comment', 'agreement', 'notes'])]
class DoctorVisit extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['visited_at' => 'date', 'next_visit_at' => 'date'];
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
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @param Builder<DoctorVisit> $query */
    public function scopePendingFollowup(Builder $query): void
    {
        $query->whereNotNull('next_visit_at')
            ->where(fn ($q) => $q->whereNull('visited_at')->orWhereColumn('next_visit_at', '>', 'visited_at'))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('doctor_visits as followup')
                    ->whereColumn('followup.doctor_id', 'doctor_visits.doctor_id')
                    ->whereColumn('followup.branch_id', 'doctor_visits.branch_id')
                    ->whereColumn('followup.visited_at', '>=', 'doctor_visits.next_visit_at')
                    ->whereNull('followup.deleted_at');
            });
    }
}
