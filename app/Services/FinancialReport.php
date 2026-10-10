<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\MedicalCase;
use App\Models\Payment;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class FinancialReport
{
    /**
     * @param  array<string, string|null>  $filters
     * @return array{string, string}
     */
    public static function period(User $actor, array $filters): array
    {
        abort_unless($actor->isStaff() && $actor->hasPermission(Permission::ViewFinancials), 403);
        $from = ($filters['from'] ?? null) ?: today()->toDateString();
        $to = ($filters['to'] ?? null) ?: $from;
        Validator::make(['from' => $from, 'to' => $to], ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']])->validate();
        if (! $actor->isAdmin()) {
            abort_unless($from === today()->toDateString() && $to === $from, 403);
            abort_if(! empty($filters['branch']) && ! in_array((int) $filters['branch'], AccessScope::branchIds($actor), true), 403);
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<MedicalCase>
     */
    public static function cases(User $actor, array $filters): Builder
    {
        [$from,$to] = self::period($actor, $filters);

        return AccessScope::cases($actor)->withTrashed()->whereDate('exam_date', '>=', $from)->whereDate('exam_date', '<=', $to)->when($filters['branch'] ?? null, fn ($q, $branch) => $q->where('branch_id', $branch));
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<Payment>
     */
    public static function payments(User $actor, array $filters): Builder
    {
        [$from,$to] = self::period($actor, $filters);

        return Payment::query()->when(! $actor->isAdmin(), fn ($q) => $q->whereIn('branch_id', AccessScope::branchIds($actor)))
            ->where('received_at', '>=', $from.' 00:00:00')->where('received_at', '<=', $to.' 23:59:59')
            ->when($filters['branch'] ?? null, fn ($q, $branch) => $q->where('branch_id', $branch));
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, mixed>
     */
    public static function summarize(User $actor, array $filters): array
    {
        $cases = self::cases($actor, $filters);
        $totals = (clone $cases)->whereNotNull('currency')->selectRaw('currency, SUM(base_price_minor) as base, SUM(discount_minor) as discount, SUM(final_price_minor) as billed')->groupBy('currency')->get()->keyBy('currency')->toArray();
        foreach (self::payments($actor, $filters)->selectRaw('currency, SUM(amount_minor) as collected')->groupBy('currency')->get() as $row) {
            $totals[$row->currency] = ($totals[$row->currency] ?? ['currency' => $row->currency, 'base' => 0, 'discount' => 0, 'billed' => 0]) + ['collected' => (int) $row->getAttribute('collected')];
        }
        $paidOnCases = Payment::whereIn('medical_case_id', (clone $cases)->whereNotNull('final_price_minor')->select('id'))
            ->selectRaw('currency, SUM(amount_minor) as paid')->groupBy('currency')->pluck('paid', 'currency');
        foreach ($totals as $currency => &$row) {
            $row['collected'] ??= 0;
            $row['outstanding'] = max(0, (int) $row['billed'] - (int) ($paidOnCases[$currency] ?? 0));
        }

        return ['currencies' => $totals, 'cases' => (clone $cases)->count(), 'unpriced' => (clone $cases)->whereNull('final_price_minor')->count(), 'by_exam' => (clone $cases)->with('examType')->selectRaw('exam_type_id, COUNT(*) as total')->groupBy('exam_type_id')->get()];
    }
}
