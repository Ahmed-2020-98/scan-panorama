<?php

use App\Models\Patient;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('المرضى')] class extends Component {
    use WithPagination;
    public function boot(): void { abort_unless(auth()->user()->hasPermission(\App\Enums\Permission::ViewCases),403); }

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function patients()
    {
        return Patient::query()
            ->search($this->search)
            ->withCount('cases')
            ->withMax('cases', 'exam_date')
            ->latest('id')
            ->paginate(20);
    }
}; ?>

<div class="mx-auto w-full max-w-6xl">
    <x-page-header eyebrow="سجل المرضى" title="المرضى" subtitle="سجل المرضى وكل حالاتهم">
        <x-slot:actions>
            @can('create',\App\Models\MedicalCase::class)<flux:button variant="primary" icon="plus" :href="route('cases.create')" wire:navigate>حالة جديدة</flux:button>@endcan
        </x-slot:actions>
    </x-page-header>

    <x-panel :padded="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="ابحث بالاسم أو الهاتف أو رقم الملف" class="max-w-md" clearable />
            <span class="text-sm text-zinc-500"><span class="ltr-nums font-medium text-zinc-800">{{ number_format($this->patients->total()) }}</span> مريض</span>
        </div>

        @if ($this->patients->isEmpty())
            <x-empty-state icon="users" title="لا يوجد مرضى" :text="$search ? 'لا توجد نتائج مطابقة للبحث.' : 'أضف أول مريض لبدء تسجيل الحالات.'" />
        @else
            <div class="px-4 pb-2">
                <flux:table :paginate="$this->patients">
                    <flux:table.columns>
                        <flux:table.column>رقم الملف</flux:table.column>
                        <flux:table.column>الاسم</flux:table.column>
                        <flux:table.column>الهاتف</flux:table.column>
                        <flux:table.column>النوع / السن</flux:table.column>
                        <flux:table.column>الحالات</flux:table.column>
                        <flux:table.column>آخر فحص</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->patients as $patient)
                            <flux:table.row :key="$patient->id">
                                <flux:table.cell class="ltr-nums">{{ $patient->file_number }}</flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('patients.show', $patient) }}" wire:navigate class="hover:text-brand-700">{{ $patient->name }}</a>
                                </flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $patient->phone ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $patient->genderLabel() ?? '—' }}@if ($patient->age !== null) &middot; {{ $patient->age }} سنة @endif
                                </flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" color="zinc">{{ $patient->cases_count }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $patient->cases_max_exam_date ? \Illuminate\Support\Carbon::parse($patient->cases_max_exam_date)->format('d/m/Y') : '—' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @can('create',\App\Models\MedicalCase::class)<flux:button size="sm" variant="ghost" icon="plus" :href="route('cases.create', ['patient' => $patient->id])" wire:navigate>حالة</flux:button>@endcan
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('patients.show', $patient)" wire:navigate aria-label="عرض" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-panel>
</div>
