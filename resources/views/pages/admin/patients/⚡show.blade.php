<?php

use App\Models\Patient;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[\Livewire\Attributes\Locked] public Patient $patient;

    public function mount(Patient $patient): void { $this->authorize('view',$patient); $this->patient=$patient; }

    public function render()
    {
        $this->authorize('view', $this->patient);
        return $this->view()->title($this->patient->name);
    }

    #[Computed]
    public function cases()
    {
        return $this->patient->cases()
            ->with(['doctor.user', 'branch', 'examType'])
            ->withFileCounts()
            ->orderByDesc('exam_date')
            ->get();
    }

    public function delete(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        \App\Services\RecordRecovery::delete(auth()->user(), $this->patient);
        Flux::toast(variant: 'success', text: 'تم حذف المريض.');
        $this->redirectRoute('patients.index', navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-6xl">
    <x-page-header eyebrow="ملف المريض" :title="$patient->name" :back="route('patients.index')">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                <span class="ltr-nums rounded-md bg-zinc-100 px-2 py-0.5 font-medium text-zinc-700">{{ $patient->file_number }}</span>
                @if ($patient->phone)
                    <span class="inline-flex items-center gap-1"><flux:icon.phone variant="micro" /><span class="ltr-nums">{{ $patient->phone }}</span></span>
                @endif
                @if ($patient->genderLabel())<span>{{ $patient->genderLabel() }}</span>@endif
                @if ($patient->age !== null)<span>{{ $patient->age }} سنة · مواليد {{ $patient->birth_date->year }}</span>@endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update',$patient)<flux:button icon="pencil-square" :href="route('patients.edit', $patient)" wire:navigate>تعديل</flux:button>@endcan
            @if (auth()->user()->isAdmin())
                <flux:button icon="trash" variant="ghost" class="text-red-600!" wire:click="delete" wire:confirm="حذف المريض {{ $patient->name }}؟">حذف</flux:button>
            @endif
            <flux:button variant="primary" icon="plus" :href="route('cases.create', ['patient' => $patient->id])" wire:navigate>حالة جديدة لهذا المريض</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($patient->notes)
        <flux:callout icon="information-circle" color="zinc" class="mb-6">
            <flux:callout.text class="whitespace-pre-line">{{ $patient->notes }}</flux:callout.text>
        </flux:callout>
    @endif

    <x-panel title="حالات المريض" :subtitle="$this->cases->count().' حالة'" icon="folder-open" :padded="false">
        @if ($this->cases->isEmpty())
            <x-empty-state icon="folder-open" title="لا توجد حالات لهذا المريض بعد">
                <flux:button variant="primary" icon="plus" :href="route('cases.create', ['patient' => $patient->id])" wire:navigate>إنشاء حالة</flux:button>
            </x-empty-state>
        @else
            <div class="px-4 pb-2">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>الكود</flux:table.column>
                        <flux:table.column>التاريخ</flux:table.column>
                        <flux:table.column>الفحص</flux:table.column>
                        <flux:table.column>الطبيب</flux:table.column>
                        <flux:table.column>الفرع</flux:table.column>
                        <flux:table.column>الملفات</flux:table.column>
                        <flux:table.column>الحالة</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->cases as $case)
                            <flux:table.row :key="$case->id">
                                <flux:table.cell variant="strong"><a href="{{ route('cases.show', $case) }}" wire:navigate class="ltr-nums text-brand-700 hover:underline">{{ $case->case_code }}</a></flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $case->exam_date->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell dir="ltr" class="text-end">{{ ($case->examType?->name ?? 'لم يحدد') }}</flux:table.cell>
                                <flux:table.cell>{{ ($case->doctor?->display_name ?? 'لم يحدد') }}</flux:table.cell>
                                <flux:table.cell>{{ $case->branch->name }}</flux:table.cell>
                                <flux:table.cell><x-file-type-counts :case="$case" /></flux:table.cell>
                                <flux:table.cell><x-status-badge :status="$case->status" /></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-panel>
    <div class="mt-6 space-y-5">
        @foreach($this->cases as $case)
            <x-panel :title="$case->case_code.' — '.($case->examType?->name ?? 'لم يحدد الفحص')">
                <div class="mb-3 text-sm text-zinc-600">{{ $case->exam_date->format('d/m/Y') }} · {{ $case->workflow_status->label() }}</div>
                <div class="flex flex-wrap gap-3">@forelse($case->files()->where('storage_status','ready')->get() as $file)<a class="inline-flex min-h-11 items-center rounded-lg border px-3 text-sm text-brand-700" href="{{ route('files.show',[$file,$file->isViewable() ? 'view':'download']) }}" target="_blank">{{ $file->type->shortLabel() }}: {{ $file->original_name }}</a>@empty<p class="text-sm text-zinc-500">لا توجد ملفات جاهزة.</p>@endforelse</div>
            </x-panel>
            @can('share',$case)<livewire:case-share-panel :medical-case="$case" :key="'patient-share-'.$case->id" />@endcan
        @endforeach
    </div>
</div>
