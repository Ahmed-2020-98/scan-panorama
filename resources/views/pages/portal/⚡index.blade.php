<?php

use App\Enums\CaseFileType;
use App\Models\Branch;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Setting;
use App\Models\ViewerLink;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::portal'), Title('حالاتي')] class extends Component {
    use WithPagination;
    public function boot(): void { abort_unless(auth()->user()->hasPermission(\App\Enums\Permission::ViewCases),403); }

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $exam = '';

    #[Url(except: '')]
    public string $branch = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'exam', 'branch', 'from', 'to');
    }

    private function doctorId(): int
    {
        return (int) auth()->user()->doctor?->id;
    }

    #[Computed]
    public function cases()
    {
        return MedicalCase::query()
            ->where('doctor_id', $this->doctorId())
            ->with(['patient', 'branch', 'examType', 'files' => fn ($q) => $q->where('storage_status', 'ready')])
            ->search($this->search)
            ->when($this->exam, fn ($q) => $q->where('exam_type_id', $this->exam))
            ->when($this->branch, fn ($q) => $q->where('branch_id', $this->branch))
            ->when($this->from, fn ($q) => $q->whereDate('exam_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('exam_date', '<=', $this->to))
            ->orderByDesc('exam_date')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $base = MedicalCase::where('doctor_id', $this->doctorId());

        return [
            'total' => (clone $base)->count(),
            'month' => (clone $base)->whereBetween('exam_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'new' => (clone $base)->whereHas('files', fn ($q) => $q->where('storage_status', 'ready'))->whereNull('first_opened_at')->count(),
        ];
    }

    #[Computed]
    public function viewers()
    {
        return ViewerLink::orderBy('sort')->get()->groupBy('platform');
    }

    public function hasFilters(): bool
    {
        return collect([$this->search, $this->exam, $this->branch, $this->from, $this->to])->filter()->isNotEmpty();
    }
}; ?>

<div class="space-y-6">
    @php($doctor = auth()->user()->doctor)

    <section class="film overflow-hidden rounded-2xl px-6 py-8 shadow-[0_24px_60px_-36px_rgb(4_11_25/0.8)] sm:px-9">
        <x-radiograph class="pointer-events-none absolute -bottom-12 -left-10 w-[48%] opacity-70 max-lg:hidden" label="PANORAMIC · 2D" :meta="$doctor?->code" />

        <div class="relative flex flex-wrap items-end justify-between gap-8">
            <div class="max-w-xl">
                <div class="eyebrow">صفحة الطبيب</div>
                <h1 class="mt-3 text-3xl leading-tight font-bold text-white sm:text-[2.1rem]">مرحبًا {{ $doctor?->display_name ?? auth()->user()->name }}</h1>
                <p class="mt-2 text-zinc-400">هذه صفحة مرضاك: اعرض التقارير والصور وحمّل ملفات DICOM لكل حالة.</p>
                @if ($tutorial = Setting::get('tutorial_url'))
                    <a href="{{ $tutorial }}" target="_blank" rel="noopener" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-phosphor px-4 py-2 text-sm font-semibold text-film-950 shadow-[0_0_24px_-6px_var(--color-phosphor)] transition hover:bg-phosphor-soft">
                        <flux:icon.play-circle variant="mini" />
                        لشرح طريقة الاستخدام اضغط هنا
                    </a>
                @endif
            </div>

            <dl class="grid w-full grid-cols-3 gap-px overflow-hidden rounded-xl border border-white/10 bg-white/10 sm:w-auto lg:hidden">
                @foreach ([['كل الحالات', $this->stats['total']], ['هذا الشهر', $this->stats['month']], ['جديدة', $this->stats['new']]] as [$label, $value])
                    <div class="bg-film-900/80 px-5 py-3 text-center">
                        <dt class="text-xs text-zinc-400">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl text-white"><span class="ltr-nums">{{ $value }}</span></dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <dl class="relative mt-8 hidden max-w-xl grid-cols-3 gap-px overflow-hidden rounded-xl border border-white/10 bg-white/10 lg:grid">
            @foreach ([['كل الحالات', $this->stats['total']], ['هذا الشهر', $this->stats['month']], ['جديدة لم تُفتح', $this->stats['new']]] as [$label, $value])
                <div class="bg-film-900/80 px-5 py-3.5">
                    <dt class="text-xs text-zinc-400">{{ $label }}</dt>
                    <dd class="mt-1 text-[1.7rem] leading-none text-white"><span class="ltr-nums {{ $loop->last && $value > 0 ? 'text-phosphor' : '' }}">{{ $value }}</span></dd>
                </div>
            @endforeach
        </dl>
    </section>

    @if ($this->viewers->isNotEmpty())
        <x-panel title="برامج عرض ملفات DICOM" subtitle="إذا لم يكن لديك برنامج لعرض DICOM، حمّل البرنامج المناسب لجهازك" icon="computer-desktop">
            <div class="grid gap-5 md:grid-cols-2">
                @foreach (ViewerLink::PLATFORMS as $platform => $label)
                    @if ($this->viewers->has($platform))
                        <div>
                            <div class="mb-2 flex items-center gap-2 text-sm font-medium text-zinc-600">
                                <flux:icon :name="$platform === 'mobile' ? 'device-phone-mobile' : 'computer-desktop'" variant="mini" />
                                {{ $label }}
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->viewers[$platform] as $viewer)
                                    <a href="{{ $viewer->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-800 hover:border-brand-300 hover:bg-brand-50" dir="ltr">
                                        <flux:icon.arrow-down-tray variant="micro" class="text-brand-600" />
                                        {{ $viewer->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </x-panel>
    @endif

    <x-panel title="حالات المرضى" icon="folder-open" :padded="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="ابحث باسم المريض أو الكود" class="sm:col-span-2 lg:col-span-1" clearable />
            <flux:select wire:model.live="exam">
                <flux:select.option value="">كل الفحوصات</flux:select.option>
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

        @if ($this->hasFilters())
            <div class="flex items-center justify-between px-4 pt-3 text-sm text-zinc-500">
                <span><span class="ltr-nums font-medium text-zinc-800">{{ $this->cases->total() }}</span> نتيجة</span>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">مسح الفلاتر</flux:button>
            </div>
        @endif

        @if ($this->cases->isEmpty())
            <x-empty-state icon="folder-open" :title="$this->hasFilters() ? 'لا توجد حالات مطابقة' : 'لا توجد حالات بعد'" :text="$this->hasFilters() ? 'جرّب تغيير البحث أو الفلاتر.' : 'ستظهر حالات مرضاك هنا فور تسجيلها في المركز.'" />
        @else
            {{-- Desktop table (same columns as the center's classic doctor page) --}}
            <div class="hidden px-4 pb-2 md:block">
                <flux:table :paginate="$this->cases">
                    <flux:table.columns>
                        <flux:table.column>التاريخ</flux:table.column>
                        <flux:table.column>اسم المريض</flux:table.column>
                        <flux:table.column>الفحص</flux:table.column>
                        <flux:table.column>الفرع</flux:table.column>
                        <flux:table.column>الكود</flux:table.column>
                        @foreach (CaseFileType::cases() as $type)
                            <flux:table.column>{{ $type->shortLabel() }}</flux:table.column>
                        @endforeach
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->cases as $case)
                            <flux:table.row :key="$case->id">
                                <flux:table.cell class="ltr-nums">{{ $case->exam_date->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('portal.cases.show', $case) }}" wire:navigate class="hover:text-brand-700">{{ $case->patient->name }}</a>
                                    @if ($case->first_opened_at === null && $case->files->isNotEmpty())
                                        <flux:badge size="sm" color="teal" class="ms-1">جديد</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell dir="ltr" class="text-end">{{ ($case->examType?->name ?? 'لم يحدد') }}</flux:table.cell>
                                <flux:table.cell>{{ $case->branch->name }}</flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $case->case_code }}</flux:table.cell>
                                @foreach (CaseFileType::cases() as $type)
                                    <flux:table.cell><x-portal-file-cell :files="$case->files" :type="$type" /></flux:table.cell>
                                @endforeach
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Mobile cards --}}
            <div class="divide-y divide-zinc-100 md:hidden">
                @foreach ($this->cases as $case)
                    <div class="space-y-3 p-4" wire:key="card-{{ $case->id }}">
                        <a href="{{ route('portal.cases.show', $case) }}" wire:navigate class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-zinc-900">
                                    {{ $case->patient->name }}
                                    @if ($case->first_opened_at === null && $case->files->isNotEmpty())
                                        <flux:badge size="sm" color="teal" class="ms-1">جديد</flux:badge>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-sm text-zinc-500"><span dir="ltr">{{ ($case->examType?->name ?? 'لم يحدد') }}</span> &middot; {{ $case->branch->name }}</div>
                            </div>
                            <div class="shrink-0 space-y-0.5 text-end text-xs whitespace-nowrap text-zinc-500">
                                <div><span class="ltr-nums">{{ $case->exam_date->format('d/m/Y') }}</span></div>
                                <div><span class="ltr-nums">{{ $case->case_code }}</span></div>
                            </div>
                        </a>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs text-zinc-500">
                            @foreach (CaseFileType::cases() as $type)
                                <div class="space-y-1.5 rounded-lg bg-zinc-50 p-2">
                                    <div class="truncate">{{ $type->shortLabel() }}</div>
                                    <x-portal-file-cell :files="$case->files" :type="$type" compact />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="p-4">{{ $this->cases->links() }}</div>
            </div>
        @endif
    </x-panel>
</div>
