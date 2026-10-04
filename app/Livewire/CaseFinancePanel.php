<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Models\MedicalCase;
use App\Services\CasePayments;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CaseFinancePanel extends Component
{
    #[Locked]
    public MedicalCase $medicalCase;

    #[Locked]
    public string $requestId;

    public string $amount = '';

    public string $method = 'cash';

    public function mount(MedicalCase $medicalCase): void
    {
        $this->medicalCase = $medicalCase;
        $this->requestId = (string) Str::uuid();
        $this->checkAccess();
    }

    private function checkAccess(): void
    {
        $this->authorize('view', $this->medicalCase);
        abort_unless(auth()->user()->hasPermission(Permission::ViewFinancials) && (auth()->user()->isAdmin() || $this->medicalCase->exam_date->isToday()), 403);
    }

    public function receive(): void
    {
        $this->checkAccess();
        $this->validate(['amount' => ['required', 'string'], 'method' => ['required', 'in:cash,card,transfer']]);
        CasePayments::receive(auth()->user(), $this->medicalCase, Money::minor($this->amount), $this->method, $this->requestId);
        $this->amount = '';
        $this->requestId = (string) Str::uuid();
        Flux::toast(variant: 'success', text: 'تم تسجيل التحصيل.');
    }

    public function render(): View
    {
        $this->checkAccess();

        return view('livewire.case-finance-panel', ['case' => $this->medicalCase->fresh(), 'collected' => (int) $this->medicalCase->payments()->sum('amount_minor')]);
    }
}
