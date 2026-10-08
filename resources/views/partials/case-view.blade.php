{{--
    Doctor-facing view of a single case (portal page and public share link).
    Expects: $case (with patient, doctor.user, branch, examType, files) and $fileUrl = fn (CaseFile $file, string $mode): string
    Optional: $forPatient = true hides doctor-facing notes and the doctor code (patient link).
--}}
@php($forPatient = $forPatient ?? false)
@php($grouped = $case->files->groupBy(fn ($file) => $file->type->value))

<div class="space-y-6">
    <section class="film dark overflow-hidden rounded-2xl px-6 py-7 shadow-[0_24px_60px_-36px_rgb(4_11_25/0.8)] sm:px-8">
        <x-radiograph class="pointer-events-none absolute top-1/2 -left-8 w-[42%] -translate-y-1/2 opacity-45 max-md:hidden" :scan="false" :label="strtoupper(($case->examType?->name ?? 'لم يحدد'))" :meta="$case->exam_date->format('d.m.Y')" />

        <div class="relative">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="eyebrow">ملف المريض</div>
                    <h1 class="mt-2 text-3xl leading-tight font-bold text-white">{{ $case->patient->name }}</h1>
                    <div class="mt-1 text-sm text-zinc-400">{{ ($case->doctor?->display_name ?? 'لم يحدد') }}</div>
                </div>
                <span class="ltr-nums rounded-md border border-phosphor/30 bg-phosphor/10 px-2.5 py-1 text-sm text-phosphor">{{ $case->case_code }}</span>
            </div>

            <div class="ruler mt-6"></div>

            <dl class="mt-4 flex flex-wrap gap-x-10 gap-y-4">
                @foreach ([['نوع الفحص', ($case->examType?->name ?? 'لم يحدد'), true], ['تاريخ الفحص', $case->exam_date->format('d/m/Y'), true], ['الفرع', $case->branch->name, false], ...($forPatient ? [] : [['كود الطبيب', ($case->doctor?->code ?? '—'), true]])] as [$label, $value, $mono])
                    <div>
                        <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm font-medium whitespace-nowrap text-zinc-100">
                            @if ($mono)<span class="ltr-nums">{{ $value }}</span>@else{{ $value }}@endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if (! $forPatient && $case->notes_for_doctor)
                <div class="mt-5 max-w-xl rounded-lg border border-phosphor/20 bg-phosphor/[6%] p-3 text-sm">
                    <div class="mb-1 text-xs font-medium text-phosphor">ملاحظات المركز</div>
                    <p class="whitespace-pre-line text-zinc-200">{{ $case->notes_for_doctor }}</p>
                </div>
            @endif
        </div>
    </section>

    @if(! $forPatient && $case->medical_notes)<x-panel title="التقرير والملاحظات الطبية"><p class="whitespace-pre-line text-sm">{{ $case->medical_notes }}</p></x-panel>@endif
    @if ($case->files->isEmpty())
        <x-panel>
            <x-empty-state icon="clock" title="الملفات قيد التجهيز" text="لم يتم رفع ملفات هذه الحالة بعد، سيتم إتاحتها فور جاهزيتها." />
        </x-panel>
    @endif

    @foreach (\App\Enums\CaseFileType::cases() as $type)
        @continue(! $grouped->has($type->value))

        <x-panel :title="$type->label()" :subtitle="$type->description()" :icon="$type->icon()">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($grouped[$type->value] as $file)
                    <div class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:border-brand-300 hover:shadow-[0_18px_40px_-26px_rgb(8_30_70/0.55)]">
                        @if ($file->isImage())
                            <a href="{{ $fileUrl($file, 'view') }}" target="_blank" class="film crop-marks relative block aspect-video overflow-hidden">
                                <img src="{{ $fileUrl($file, 'view') }}" alt="{{ $file->original_name }}" loading="lazy" class="relative size-full object-contain p-3 transition duration-500 group-hover:scale-[1.03]">
                                <span class="ltr-nums absolute top-2.5 left-3 rounded border border-marker/70 px-1 text-[0.65rem] leading-4 text-marker">R</span>
                            </a>
                        @elseif($file->isVideo())
                            <video controls preload="metadata" class="aspect-video w-full bg-black" src="{{ $fileUrl($file,'view') }}" aria-label="{{ $file->original_name }}"></video>
                        @else
                            <div class="film crop-marks flex aspect-video items-center justify-center overflow-hidden">
                                <div class="relative flex flex-col items-center gap-2 {{ $file->isPdf() ? 'text-phosphor-soft' : 'text-phosphor' }}">
                                    <flux:icon :name="$file->isPdf() ? 'document-text' : 'cube'" class="size-11 opacity-90" />
                                    <span class="ltr-nums text-[0.7rem] tracking-widest text-zinc-400 uppercase">{{ $file->extension() }}</span>
                                </div>
                            </div>
                        @endif
                        <div class="flex flex-1 flex-col gap-3 p-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-zinc-800" dir="ltr" style="text-align: right">{{ $file->original_name }}</div>
                                <div class="text-xs text-zinc-500"><span class="ltr-nums">{{ $file->humanSize() }}</span></div>
                            </div>
                            <div class="mt-auto flex gap-2">
                                @if ($file->isViewable())
                                    <flux:button size="sm" icon="eye" :href="$fileUrl($file, 'view')" target="_blank" class="flex-1">عرض</flux:button>
                                @endif
                                <flux:button size="sm" :variant="$file->isViewable() ? 'ghost' : 'primary'" icon="arrow-down-tray" :href="$fileUrl($file, 'download')" class="flex-1">تحميل</flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($type === \App\Enums\CaseFileType::Dicom)
                <p class="mt-4 text-xs text-zinc-500">افتح ملف DICOM بعد تحميله باستخدام أحد برامج العرض (مثل Romexis أو OnDemand3D).</p>
            @endif
        </x-panel>
    @endforeach
</div>
