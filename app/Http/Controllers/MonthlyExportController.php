<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\MedicalCase;
use App\Support\AccessScope;
use App\Support\ActivityLogger;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Month-by-month Excel exports of cases and expenses (open in Excel or import
 * into Google Sheets).
 */
class MonthlyExportController extends Controller
{
    public function cases(Request $request): BinaryFileResponse
    {
        [$month, $from, $to] = $this->month($request);
        $branch = $request->integer('branch') ?: null;
        $money = $request->user()->hasPermission(Permission::ViewFinancials);

        $header = ['كود الحالة', 'التاريخ', 'المريض', 'رقم الملف', 'الموبايل', 'السن', 'الطبيب', 'الفحص', 'الفرع', 'حالة العمل'];
        if ($money) {
            array_push($header, 'السعر', 'المدفوع', 'المتبقي', 'العملة');
        }
        $header[] = 'ملاحظات';
        $rows = [$header];

        MedicalCase::query()->with(['patient', 'doctor.user', 'examType', 'branch'])->withSum('payments', 'amount_minor')
            ->whereDate('exam_date', '>=', $from)->whereDate('exam_date', '<=', $to)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch))
            ->orderBy('exam_date')->orderBy('id')
            ->chunk(500, function ($cases) use (&$rows, $money) {
                foreach ($cases as $case) {
                    $row = [
                        $case->case_code, $case->exam_date->format('Y-m-d'), $case->patient->name, $case->patient->file_number,
                        $case->patient->phone, $case->patient->age, $case->doctor?->display_name, $case->examType?->name,
                        $case->branch->name, $case->workflow_status->label(),
                    ];
                    if ($money) {
                        $paid = (int) $case->getAttribute('payments_sum_amount_minor');
                        array_push($row, self::amount($case->final_price_minor), self::amount($paid), $case->final_price_minor === null ? null : self::amount(max($case->final_price_minor - $paid, 0)), $case->currency);
                    }
                    $row[] = $case->notes_internal;
                    $rows[] = $row;
                }
            });

        ActivityLogger::log('export.cases', null, ['month' => $month, 'rows' => count($rows) - 1]);

        return Xlsx::download('cases-'.$month.'.xlsx', ['الحالات '.$month => $rows]);
    }

    public function expenses(Request $request): BinaryFileResponse
    {
        [$month, $from, $to] = $this->month($request);
        $user = $request->user();
        $branch = $request->integer('branch') ?: null;

        $expenses = Expense::query()->with(['branch', 'user'])
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('branch_id', AccessScope::branchIds($user)))
            ->when($branch, fn ($q) => $q->where('branch_id', $branch))
            ->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)
            ->orderBy('spent_on')->orderBy('id')->get();

        $rows = [['التاريخ', 'البند', 'الفرع', 'المبلغ', 'العملة', 'الموظف', 'عدد الشيفتات', 'ملاحظات']];
        foreach ($expenses as $expense) {
            $rows[] = [$expense->spent_on->format('Y-m-d'), $expense->categoryLabel(), $expense->branch_id === null ? 'كل الفروع' : $expense->branch->name, self::amount($expense->amount_minor), $expense->currency, $expense->user?->name, $expense->shifts, $expense->note];
        }
        $rows[] = [];
        foreach ($expenses->groupBy(fn (Expense $e) => $e->categoryLabel()) as $label => $group) {
            $rows[] = ['', $label, '', self::amount((int) $group->sum('amount_minor'))];
        }
        $rows[] = ['', 'الإجمالي', '', self::amount((int) $expenses->sum('amount_minor'))];

        ActivityLogger::log('export.expenses', null, ['month' => $month, 'rows' => $expenses->count()]);

        return Xlsx::download('expenses-'.$month.'.xlsx', ['المصروفات '.$month => $rows]);
    }

    /** @return array{string, string, string} */
    private function month(Request $request): array
    {
        $month = (string) $request->query('month', today()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1, 422);
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);

        return [$month, $start->toDateString(), $start->endOfMonth()->toDateString()];
    }

    private static function amount(?int $minor): int|float|null
    {
        return $minor === null ? null : ($minor % 100 === 0 ? intdiv($minor, 100) : $minor / 100);
    }
}
