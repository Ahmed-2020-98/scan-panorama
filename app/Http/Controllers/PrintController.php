<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\MedicalCase;
use App\Services\FinancialReport;
use App\Support\AccessScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Printer-friendly pages (A4 / receipt): the browser's print dialog opens on load. */
class PrintController extends Controller
{
    public function case(MedicalCase $case): View
    {
        Gate::authorize('view', $case);
        $case->load(['patient', 'doctor.user', 'branch', 'examType', 'files' => fn ($q) => $q->where('storage_status', 'ready'), 'creator']);

        return view('print.case', ['case' => $case]);
    }

    public function receipt(Request $request, MedicalCase $case): View
    {
        Gate::authorize('view', $case);
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::ViewFinancials) && ($user->hasPermission(Permission::ViewAllFinancials) || $case->exam_date->isToday()), 403);
        $case->load(['patient', 'doctor.user']);

        return view('print.receipt', ['case' => $case]);
    }

    public function accounts(Request $request): View
    {
        $user = $request->user();
        $filters = ['from' => $request->query('from'), 'to' => $request->query('to'), 'branch' => $request->query('branch')];
        [$from, $to] = FinancialReport::period($user, $filters);
        $summary = FinancialReport::summarize($user, $filters);
        $cases = FinancialReport::cases($user, $filters)->with(['patient', 'branch', 'examType', 'doctor.user'])->withSum('payments', 'amount_minor')->orderBy('exam_date')->get();
        $expenses = $user->hasPermission(Permission::ManageExpenses)
            ? Expense::when(! $user->isAdmin(), fn ($q) => $q->whereIn('branch_id', AccessScope::branchIds($user)))
                ->when($filters['branch'], fn ($q, $b) => $q->where('branch_id', $b))
                ->whereDate('spent_on', '>=', $from)->whereDate('spent_on', '<=', $to)->orderBy('spent_on')->get()
            : null;

        return view('print.accounts', compact('from', 'to', 'summary', 'cases', 'expenses') + ['branch' => $filters['branch'] ? AccessScope::branches($user)->find($filters['branch']) : null]);
    }
}
