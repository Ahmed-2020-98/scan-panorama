<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessScope
{
    /** @return array<int, int> */
    public static function branchIds(User $actor): array
    {
        if ($actor->branch_id) {
            return [(int) $actor->branch_id];
        }
        if ($actor->isDoctor()) {
            return Doctor::where('user_id', $actor->id)->first()?->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->values()->all() ?? [];
        }

        return [];
    }

    /**
     * @template T of MedicalCase
     *
     * @param  Builder<T>  $query
     */
    public static function applyCases(Builder $query, User $actor): void
    {
        if ($actor->isAdmin()) {
            return;
        }
        $query->whereIn('medical_cases.branch_id', self::branchIds($actor));
        if ($actor->isDoctor()) {
            $query->where('doctor_id', Doctor::where('user_id', $actor->id)->value('id') ?? 0);
        } elseif ($actor->isTechnician()) {
            $query->where('technician_id', $actor->id);
        }
    }

    /** @return Builder<MedicalCase> */
    public static function cases(User $actor): Builder
    {
        $query = MedicalCase::withoutGlobalScope('access');
        self::applyCases($query, $actor);

        return $query;
    }

    public static function allowsCase(User $actor, MedicalCase $case): bool
    {
        return self::cases($actor)->whereKey($case->id)->exists() && $case->patient !== null && ! $case->patient->trashed();
    }

    /** @return Builder<Patient> */
    public static function patients(User $actor): Builder
    {
        return Patient::withoutGlobalScope('access')->when(! $actor->isAdmin(), function ($q) use ($actor) {
            $q->where(function ($q) use ($actor) {
                $q->whereHas('cases', fn ($c) => self::applyCases($c, $actor));
                if ($actor->isStaff()) {
                    $q->orWhereHas('branches', fn ($b) => $b->whereIn('branches.id', self::branchIds($actor)));
                }
            });
        });
    }

    /** @return Builder<Branch> */
    public static function branches(User $actor): Builder
    {
        return Branch::query()->when(! $actor->isAdmin(), fn ($q) => $q->whereIn('id', self::branchIds($actor)));
    }

    /** @return Builder<Doctor> */
    public static function doctors(User $actor): Builder
    {
        return Doctor::query()->when(! $actor->isAdmin(), fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', self::branchIds($actor))));
    }
}
