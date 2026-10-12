<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorVisit;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccessScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How many cases each doctor refers, how that is trending, and how it moved
 * around the last visit. Status drives the "doctor stopped sending" alerts.
 */
class DoctorPerformance
{
    public const STATUSES = [
        'stopped' => ['توقف عن الإرسال', 'red'],
        'declining' => ['تراجع', 'amber'],
        'growing' => ['في تحسن', 'green'],
        'steady' => ['منتظم', 'blue'],
        'new' => ['جديد', 'violet'],
        'inactive' => ['لا توجد حالات', 'zinc'],
    ];

    /**
     * Keyed by doctor id: doctor, last30, prev30, last90, change, last_case, first_case,
     * last_visit, next_visit, before_visit, after_visit, status, quiet_days.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(User $actor, ?int $branchId = null): Collection
    {
        return collect(self::compute($actor, $branchId));
    }

    /** @return array<int, array<string, mixed>> */
    private static function compute(User $actor, ?int $branchId): array
    {
        $today = CarbonImmutable::today();
        $quietDays = max(7, (int) Setting::get('drop_alert_days', 14));
        $dropPercent = min(90, max(20, (int) Setting::get('drop_alert_percent', 50)));

        $doctors = AccessScope::doctors($actor)->with('user')->active()
            ->when($branchId, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId)))
            ->orderByName()->get();
        if ($doctors->isEmpty()) {
            return [];
        }

        // Daily counts for the last year: enough for trends and before/after a visit.
        $daily = AccessScope::cases($actor)->whereIn('doctor_id', $doctors->modelKeys())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('exam_date', '>=', $today->subDays(400))
            ->selectRaw('doctor_id, DATE(exam_date) as day, COUNT(*) as total')
            ->groupBy('doctor_id', DB::raw('DATE(exam_date)'))
            ->get()->groupBy('doctor_id');
        $bounds = AccessScope::cases($actor)->whereIn('doctor_id', $doctors->modelKeys())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('doctor_id, MIN(exam_date) as first_case, MAX(exam_date) as last_case')
            ->groupBy('doctor_id')->get()->keyBy('doctor_id');

        $visits = DoctorVisit::whereIn('doctor_id', $doctors->modelKeys())
            ->when(! $actor->isAdmin(), fn ($q) => $q->whereIn('branch_id', AccessScope::branchIds($actor)))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get(['doctor_id', 'visited_at', 'next_visit_at'])->groupBy('doctor_id');

        return $doctors->map(function (Doctor $doctor) use ($daily, $bounds, $visits, $today, $quietDays, $dropPercent) {
            $days = collect($daily->get($doctor->id, []))->mapWithKeys(fn ($r) => [(string) $r->getAttribute('day') => (int) $r->getAttribute('total')]);
            $count = fn (CarbonImmutable $from, CarbonImmutable $to) => $days->filter(fn ($n, $d) => $d >= $from->toDateString() && $d < $to->toDateString())->sum();
            $tomorrow = $today->addDay();
            $last30 = $count($today->subDays(29), $tomorrow);
            $prev30 = $count($today->subDays(59), $today->subDays(29));
            $last90 = $count($today->subDays(89), $tomorrow);

            $b = $bounds->get($doctor->id);
            $first = $b?->getAttribute('first_case') ? CarbonImmutable::parse($b->getAttribute('first_case'))->startOfDay() : null;
            $last = $b?->getAttribute('last_case') ? CarbonImmutable::parse($b->getAttribute('last_case'))->startOfDay() : null;

            $doctorVisits = $visits->get($doctor->id, collect());
            $lastVisit = $doctorVisits->whereNotNull('visited_at')->max('visited_at');
            $lastVisit = $lastVisit ? CarbonImmutable::parse($lastVisit)->startOfDay() : null;
            $next = $doctorVisits->whereNotNull('next_visit_at')->filter(fn ($v) => ! $lastVisit || $v->next_visit_at->gt($lastVisit))->min('next_visit_at');
            $next = $next ? CarbonImmutable::parse($next)->startOfDay() : null;

            $before = $after = null;
            if ($lastVisit) {
                $window = min(30, max(1, (int) $lastVisit->diffInDays($tomorrow)));
                $before = $count($lastVisit->subDays($window), $lastVisit);
                $after = $count($lastVisit, $lastVisit->addDays($window));
            }

            $quiet = $last ? (int) $last->diffInDays($today) : null;
            $recentBase = $count($today->subDays($quietDays + 60), $today->subDays($quietDays));
            $status = match (true) {
                $last === null => 'inactive',
                $first !== null && $first->gte($today->subDays(30)) => 'new',
                $quiet !== null && $quiet >= $quietDays && $recentBase >= 3 => 'stopped',
                $prev30 >= 4 && $last30 <= $prev30 * (100 - $dropPercent) / 100 => 'declining',
                $last30 >= 3 && $last30 > $prev30 * 1.25 => 'growing',
                default => $last30 === 0 && $prev30 === 0 ? 'inactive' : 'steady',
            };

            return [
                'doctor' => $doctor, 'last30' => $last30, 'prev30' => $prev30, 'last90' => $last90,
                'change' => $prev30 > 0 ? (int) round(($last30 - $prev30) * 100 / $prev30) : null,
                'last_case' => $last, 'first_case' => $first, 'last_visit' => $lastVisit, 'next_visit' => $next,
                'before_visit' => $before, 'after_visit' => $after, 'status' => $status, 'quiet_days' => $quiet,
            ];
        })->keyBy(fn ($row) => $row['doctor']->id)->all();
    }
}
