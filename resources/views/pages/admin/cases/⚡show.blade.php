<?php

use App\Enums\CaseFileType;
use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Support\ActivityLogger;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[\Livewire\Attributes\Locked] public MedicalCase $medicalCase;

    public function mount(MedicalCase $case): void
    {
        $this->authorize('view',$case);
        $this->medicalCase = $case;
    }

    public function boot(): void { if (isset($this->medicalCase)) { $this->authorize('view',$this->medicalCase); } }

    public function render()
    {
        return $this->view()->title('حالة '.$this->medicalCase->case_code);
    }

    #[Computed]
    public function filesByType()
    {
        return $this->medicalCase->files()->with('uploader')->get()->groupBy(fn (CaseFile $file) => $file->type->value);
    }

    #[Computed]
    public function activities()
    {
        return $this->medicalCase->activities()->with('user')->limit(12)->get();
    }

    public function deleteFile(int $fileId): void
    {
        $file = $this->medicalCase->files()->findOrFail($fileId);
        $this->authorize('delete', $file);

        \App\Services\RecordRecovery::delete(auth()->user(),$file);

        Flux::toast(variant: 'success', text: 'تم حذف الملف.');
    }

    public function markCopied(): void
    {
        $this->authorize('share',$this->medicalCase);
        $this->medicalCase->markShared();
        ActivityLogger::log('share.copied', $this->medicalCase);

        Flux::toast(variant: 'success', text: 'تم نسخ الرابط.');
    }

    public function markWhatsapp(): void
    {
        $this->authorize('share',$this->medicalCase);
        $this->medicalCase->markShared();
        ActivityLogger::log('share.whatsapp', $this->medicalCase);
    }

    public function regenerateLink(): void
    {
        $this->authorize('share',$this->medicalCase);
        $this->authorize('update', $this->medicalCase);

        $this->medicalCase->regenerateShareLink();
        ActivityLogger::log('share.regenerated', $this->medicalCase);

        Flux::toast(variant: 'success', text: 'تم إصدار رابط جديد، الرابط القديم لم يعد يعمل.');
    }

    public function revokeLink(): void
    {
        $this->authorize('share',$this->medicalCase);
        $this->authorize('update', $this->medicalCase);

        $this->medicalCase->revokeShareLink();
        ActivityLogger::log('share.revoked', $this->medicalCase);

        Flux::toast(variant: 'warning', text: 'تم إلغاء رابط المشاركة.');
    }

    public function toggleArchive(): void
    {
        $this->authorize('update', $this->medicalCase);

        $archived = $this->medicalCase->archived_at === null;
        $this->medicalCase->forceFill(['archived_at' => $archived ? now() : null])->save();
        ActivityLogger::log($archived ? 'case.archived' : 'case.restored', $this->medicalCase);

        Flux::toast(text: $archived ? 'تمت أرشفة الحالة.' : 'تم إلغاء الأرشفة.');
    }

    public function deleteCase(): void
    {
        $this->authorize('delete', $this->medicalCase);

        \App\Services\RecordRecovery::delete(auth()->user(),$this->medicalCase);

        Flux::toast(variant: 'success', text: 'تم حذف الحالة.');
        $this->redirectRoute('cases.index', navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-7xl">
    @php
        $case = $medicalCase->loadMissing(['patient', 'doctor.user', 'branch', 'examType', 'creator']);
        $canUpload = auth()->user()->can('uploadFiles', $case);
        $status = $case->status;
    @endphp

    <section class="film dark mb-6 overflow-hidden rounded-2xl px-6 pt-5 pb-7 shadow-[0_24px_60px_-36px_rgb(5_30_32/0.8)] sm:px-8">
        <x-radiograph class="pointer-events-none absolute top-1/2 -left-6 w-[40%] -translate-y-1/2 opacity-40 max-lg:hidden" :scan="false" :label="strtoupper(($case->examType?->name ?? 'لم يحدد'))" :meta="$case->case_code" />

        <div class="relative">
            <a href="{{ route('cases.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-zinc-400 transition hover:text-white">
                <flux:icon.arrow-right variant="micro" />
                كل الحالات
            </a>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-5">
                <div class="min-w-0">
                    <div class="eyebrow">ملف الحالة</div>
                    <h1 class="mt-2 text-3xl leading-tight font-bold text-white">{{ $case->patient->name }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2.5 text-sm text-zinc-300">
                        <span class="ltr-nums rounded-md border border-phosphor/30 bg-phosphor/10 px-2 py-0.5 text-phosphor">{{ $case->case_code }}</span>
                        <x-status-badge :status="$status" /><flux:badge :color="$case->workflow_status->color()">{{ $case->workflow_status->label() }}</flux:badge>
                        <span dir="ltr">{{ ($case->examType?->name ?? 'لم يحدد') }}</span>
                        <span class="text-zinc-500">&middot;</span>
                        <span>{{ $case->exam_date->translatedFormat('j F Y') }}</span>
                    </div>
                </div>

                <div class="dark flex flex-wrap items-center gap-2">
                    <flux:button icon="pencil-square" :href="route('cases.edit', $case)" wire:navigate>تعديل</flux:button>
                    <flux:dropdown align="end">
                        <flux:button icon="ellipsis-horizontal" aria-label="المزيد" />
                        <flux:menu>
                            <flux:menu.item icon="document-duplicate" :href="route('cases.create', ['patient' => $case->patient_id, 'doctor' => $case->doctor_id])" wire:navigate>حالة جديدة لنفس المريض</flux:menu.item>
                            <flux:menu.item :icon="$case->archived_at ? 'archive-box-x-mark' : 'archive-box'" wire:click="toggleArchive">
                                {{ $case->archived_at ? 'إلغاء الأرشفة' : 'أرشفة الحالة' }}
                            </flux:menu.item>
                            @can('delete', $case)
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="deleteCase" wire:confirm="حذف الحالة {{ $case->case_code }}؟ لن تظهر في القوائم ولن يعمل رابطها.">حذف الحالة</flux:menu.item>
                            @endcan
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </div>
    </section>

    @if ($case->archived_at)
        <flux:callout icon="archive-box" color="zinc" class="mb-6">
            <flux:callout.heading>هذه الحالة مؤرشفة منذ {{ $case->archived_at->translatedFormat('j F Y') }}</flux:callout.heading>
            <flux:callout.text>لا تظهر في القائمة الافتراضية ولا يمكن رفع ملفات جديدة لها.</flux:callout.text>
        </flux:callout>
    @elseif ($status === \App\Enums\CaseStatus::Incomplete)
        <flux:callout icon="cloud-arrow-up" color="amber" class="mb-6">
            <flux:callout.heading>الخطوة التالية: ارفع ملفات الحالة</flux:callout.heading>
            <flux:callout.text>ارفع التقارير والصور وReferral sheet وملف DICOM، ثم انسخ رابط المشاركة أو أرسله للطبيب عبر واتساب.</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="بيانات الحالة" icon="identification">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-3">
                    <x-info-item label="المريض">
                        <a href="{{ route('patients.show', $case->patient) }}" wire:navigate class="text-brand-700 hover:underline">{{ $case->patient->name }}</a>
                    </x-info-item>
                    <x-info-item label="رقم الملف"><span class="ltr-nums">{{ $case->patient->file_number }}</span></x-info-item>
                    <x-info-item label="هاتف المريض">@if ($case->patient->phone)<span class="ltr-nums">{{ $case->patient->phone }}</span>@endif</x-info-item>
                    <x-info-item label="الطبيب">{{ ($case->doctor?->display_name ?? 'لم يحدد') }} <span class="ltr-nums text-xs text-zinc-500">({{ ($case->doctor?->code ?? '—') }})</span></x-info-item>
                    <x-info-item label="الفرع">{{ $case->branch->name }}</x-info-item>
                    <x-info-item label="نوع الفحص"><span dir="ltr">{{ ($case->examType?->name ?? 'لم يحدد') }}</span></x-info-item>
                    <x-info-item label="تاريخ الفحص"><span class="ltr-nums">{{ $case->exam_date->format('d/m/Y') }}</span></x-info-item>
                    <x-info-item label="أنشأها">{{ $case->creator?->name }}</x-info-item>
                    <x-info-item label="تاريخ الإنشاء"><span class="ltr-nums">{{ $case->created_at->format('d/m/Y H:i') }}</span></x-info-item>
                </dl>

                @if ($case->notes_for_doctor || $case->notes_internal)
                    <div class="mt-5 grid gap-3 border-t border-zinc-100 pt-4 sm:grid-cols-2">
                        @if ($case->notes_for_doctor)
                            <div class="rounded-lg bg-brand-50 p-3 text-sm">
                                <div class="mb-1 text-xs font-medium text-brand-700">ملاحظات للطبيب</div>
                                <p class="whitespace-pre-line text-zinc-700">{{ $case->notes_for_doctor }}</p>
                            </div>
                        @endif
                        @if ($case->notes_internal)
                            <div class="rounded-lg bg-amber-50 p-3 text-sm">
                                <div class="mb-1 text-xs font-medium text-amber-700">ملاحظات داخلية</div>
                                <p class="whitespace-pre-line text-zinc-700">{{ $case->notes_internal }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </x-panel>

            <livewire:case-clinical-panel :medical-case="$case" :key="'staff-clinical-'.$case->id" />
        </div>

        <div class="space-y-6">
            @can('share',$case)<livewire:case-share-panel :medical-case="$case" :key="'case-share-'.$case->id" />@endcan
            @if(auth()->user()->hasPermission(\App\Enums\Permission::ViewFinancials) && (auth()->user()->isAdmin() || $case->exam_date->isToday()))<livewire:case-finance-panel :medical-case="$case" :key="'case-finance-'.$case->id" />@endif
            <x-panel title="متابعة الطبيب" icon="eye">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">أول فتح</dt>
                        <dd class="ltr-nums font-medium text-zinc-800">{{ $case->first_opened_at?->format('d/m/Y H:i') ?? 'لم تُفتح بعد' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">آخر فتح</dt>
                        <dd class="ltr-nums font-medium text-zinc-800">{{ $case->last_opened_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">مرات الوصول</dt>
                        <dd class="ltr-nums font-medium text-zinc-800">{{ $case->open_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">تمت المشاركة</dt>
                        <dd class="ltr-nums font-medium text-zinc-800">{{ $case->shared_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </x-panel>

            <x-panel title="سجل النشاط" icon="clock" :padded="false">
                @if ($this->activities->isEmpty())
                    <p class="p-5 text-sm text-zinc-400">لا يوجد نشاط مسجل.</p>
                @else
                    <ol class="divide-y divide-zinc-100">
                        @foreach ($this->activities as $activity)
                            <li class="px-5 py-2.5 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-zinc-800">{{ $activity->label() }}</span>
                                    <span class="shrink-0 text-xs text-zinc-400" title="{{ $activity->created_at->format('d/m/Y H:i') }}">{{ $activity->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="truncate text-xs text-zinc-500">
                                    {{ $activity->actorName() }}
                                    @if (isset($activity->meta['name'])) &middot; <span dir="ltr">{{ $activity->meta['name'] }}</span> @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-panel>
        </div>
    </div>
</div>
