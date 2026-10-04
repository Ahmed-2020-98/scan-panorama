<?php

use App\Enums\CaseStatus;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\ExamType;
use App\Models\MedicalCase;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('الحالات')] class extends Component {
    use WithPagination;
    public function boot(): void { abort_unless(auth()->user()->hasPermission(\App\Enums\Permission::ViewCases),403); }

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $doctor = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $exam = '';

    #[Url(except: '')] public string $workflow = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'doctor', 'branch', 'exam', 'status', 'workflow', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'doctor', 'branch', 'exam', 'status', 'workflow', 'from', 'to');
        $this->resetPage();
    }

    #[Computed]
    public function cases()
    {
        return MedicalCase::query()
            ->with(['patient', 'doctor.user', 'branch', 'examType'])
            ->withFileCounts()
            ->search($this->search)
            ->withStatus($this->status ?: null)
            ->when($this->workflow, fn($q) => $q->where('workflow_status',$this->workflow))
            ->when($this->doctor, fn ($q) => $q->where('doctor_id', $this->doctor))
            ->when($this->branch, fn ($q) => $q->where('branch_id', $this->branch))
            ->when($this->exam, fn ($q) => $q->where('exam_type_id', $this->exam))
            ->when($this->from, fn ($q) => $q->whereDate('exam_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('exam_date', '<=', $this->to))
            ->orderByDesc('exam_date')
            ->orderByDesc('id')
            ->paginate(20);
    }

    #[Computed]
    public function doctors()
    {
        return \App\Support\AccessScope::doctors(auth()->user())->with('user')->orderByName()->get();
    }

    public function hasFilters(): bool
    {
        return collect([$this->search, $this->doctor, $this->branch, $this->exam, $this->status, $this->workflow, $this->from, $this->to])->filter()->isNotEmpty();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl">
    <x-page-header eyebrow="سجل الأشعة" title="الحالات" subtitle="كل حالات الأشعة مع ملفاتها وحالة مشاركتها">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('cases.create')" wire:navigate>حالة جديدة</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :padded="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="اسم المريض، الكود، أو الهاتف" class="sm:col-span-2" clearable />
            <flux:select wire:model.live="doctor">
                <flux:select.option value="">كل الأطباء</flux:select.option>
                @foreach ($this->doctors as $doctorOption)
                    <flux:select.option value="{{ $doctorOption->id }}">{{ $doctorOption->display_name }} ({{ $doctorOption->code }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="workflow" label="سير الفحص"><flux:select.option value="">كل مراحل الفحص</flux:select.option>@foreach(\App\Enums\WorkflowStatus::cases() as $state)<flux:select.option :value="$state->value">{{ $state->label() }}</flux:select.option>@endforeach</flux:select>
            <flux:select wire:model.live="status" label="المشاركة والملفات">
                <flux:select.option value="">كل الحالات (غير المؤرشفة)</flux:select.option>
                @foreach (CaseStatus::cases() as $statusOption)
                    <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="exam">
                <flux:select.option value="">كل أنواع الفحص</flux:select.option>
                @foreach (ExamType::ordered()->get() as $examOption)
                    <flux:select.option value="{{ $examOption->id }}">{{ $examOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="branch">
                <flux:select.option value="">كل الفروع</flux:select.option>
                @foreach (\App\Support\AccessScope::branches(auth()->user())->orderBy('name')->get() as $branchOption)
                    <flux:select.option value="{{ $branchOption->id }}">{{ $branchOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input.group>
                <flux:input.group.prefix>من</flux:input.group.prefix>
                <flux:input type="date" wire:model.live="from" aria-label="من تاريخ" />
            </flux:input.group>
            <flux:input.group>
                <flux:input.group.prefix>إلى</flux:input.group.prefix>
                <flux:input type="date" wire:model.live="to" aria-label="إلى تاريخ" />
            </flux:input.group>
        </div>

        <div class="flex items-center justify-between px-4 py-2.5 text-sm text-zinc-500">
            <span>
                <span class="ltr-nums font-medium text-zinc-800">{{ number_format($this->cases->total()) }}</span> حالة
            </span>
            @if ($this->hasFilters())
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">مسح الفلاتر</flux:button>
            @endif
        </div>

        @if ($this->cases->isEmpty())
            <x-empty-state icon="folder-open" title="لا توجد حالات مطابقة" text="جرّب تغيير الفلاتر أو البحث بكلمة أخرى." />
        @else
            <div class="px-4 pb-2">
                <flux:table :paginate="$this->cases">
                    <flux:table.columns>
                        <flux:table.column>الكود</flux:table.column>
                        <flux:table.column>التاريخ</flux:table.column>
                        <flux:table.column>المريض</flux:table.column>
                        <flux:table.column>الفحص</flux:table.column>
                        <flux:table.column>الطبيب</flux:table.column>
                        <flux:table.column>الفرع</flux:table.column>
                        <flux:table.column>الملفات</flux:table.column>
                        <flux:table.column>الحالة</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->cases as $case)
                            <flux:table.row :key="$case->id" wire:key="case-{{ $case->id }}">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('cases.show', $case) }}" wire:navigate class="ltr-nums text-brand-700 hover:underline">{{ $case->case_code }}</a>
                                </flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $case->exam_date->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('cases.show', $case) }}" wire:navigate class="hover:text-brand-700">{{ $case->patient->name }}</a>
                                </flux:table.cell>
                                <flux:table.cell dir="ltr" class="text-end">{{ ($case->examType?->name ?? 'لم يحدد') }}</flux:table.cell>
                                <flux:table.cell>{{ ($case->doctor?->display_name ?? 'لم يحدد') }}</flux:table.cell>
                                <flux:table.cell>{{ $case->branch->name }}</flux:table.cell>
                                <flux:table.cell><x-file-type-counts :case="$case" /></flux:table.cell>
                                <flux:table.cell><flux:badge :color="$case->workflow_status->color()">{{ $case->workflow_status->label() }}</flux:badge><x-status-badge :status="$case->status" /></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-panel>
</div>
