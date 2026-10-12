@php
    use App\Enums\CaseFileType;
    $fileUrl = fn (\App\Models\CaseFile $file, string $mode) => route('doctor-link.file', [$token, $file, $mode]);
@endphp
<x-layouts::public :title="'حالات '.$doctor->display_name">
    @auth
        @if (auth()->user()->isStaff())
            <flux:callout icon="information-circle" color="sky" class="mb-6">
                <flux:callout.text>أنت تشاهد رابط الطبيب كما يراه. زيارتك لا تُحتسب فتحًا من الطبيب.</flux:callout.text>
            </flux:callout>
        @endif
    @endauth

    <div class="mb-5">
        <div class="eyebrow">حالات الطبيب</div>
        <h1 class="mt-1 font-display text-2xl font-bold text-zinc-900">{{ $doctor->display_name }}</h1>
        <p class="mt-1 text-sm text-zinc-500"><span class="ltr-nums">{{ $cases->count() }}</span> حالة @if ($search !== '') مطابقة للبحث @endif</p>
    </div>

    @php($openBooklet = \App\Models\BookletRequest::where('doctor_id', $doctor->id)->where('status', 'requested')->first())
    <div class="mb-5 rounded-xl border border-zinc-200 bg-white p-4" x-data="{ open: false }">
        @if (session('booklet_requested') || $openBooklet)
            <div class="flex items-center gap-3 text-sm text-green-800"><flux:icon.check-circle class="size-5 text-green-600" /> تم استلام طلب دفتر التحويل{{ $openBooklet ? ' ('.$openBooklet->quantity.' دفتر)' : '' }}، وسيتواصل معك المركز قريبًا.</div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><div class="font-medium">دفتر التحويل خلص؟</div><div class="text-sm text-zinc-500">اطلب دفتر جديد وهيوصلك من المركز.</div></div>
                <flux:button icon="book-open" variant="primary" x-on:click="open = ! open">طلب دفتر</flux:button>
            </div>
            <form method="POST" action="{{ route('doctor-link.booklet', $token) }}" x-show="open" x-cloak class="mt-4 grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-end">
                @csrf
                <flux:input name="quantity" type="number" min="1" max="20" value="1" label="عدد الدفاتر" />
                <flux:input name="note" label="ملاحظة (اختياري)" placeholder="مثلاً: العنوان أو وقت مناسب للتسليم" />
                <flux:button type="submit" variant="primary">إرسال الطلب</flux:button>
            </form>
        @endif
    </div>

    <x-panel :padded="false">
        <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-zinc-100 p-4">
            <flux:input name="q" :value="$search" icon="magnifying-glass" placeholder="ابحث باسم المريض أو الكود أو الهاتف" class="max-w-md flex-1" />
            <flux:button type="submit" variant="primary">بحث</flux:button>
            @if ($search !== '')<flux:button :href="route('doctor-link.index', $token)" variant="ghost" icon="x-mark">مسح</flux:button>@endif
        </form>

        @if ($cases->isEmpty())
            <x-empty-state icon="folder-open" :title="$search !== '' ? 'لا توجد حالات مطابقة' : 'لا توجد حالات بعد'" :text="$search !== '' ? 'جرّب البحث بكلمة أخرى.' : 'ستظهر حالات مرضاك هنا فور تسجيلها في المركز.'" />
        @else
            <div class="hidden px-4 pb-2 md:block">
                <flux:table>
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
                        @foreach ($cases as $case)
                            <flux:table.row :key="$case->id">
                                <flux:table.cell class="ltr-nums">{{ $case->exam_date->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('doctor-link.case', [$token, $case->id]) }}" class="hover:text-brand-700">{{ $case->patient->name }}</a>
                                    @if ($case->first_opened_at === null && $case->files->isNotEmpty())
                                        <flux:badge size="sm" color="blue" class="ms-1">جديد</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell dir="ltr" class="text-end">{{ $case->examType?->name ?? 'لم يحدد' }}</flux:table.cell>
                                <flux:table.cell>{{ $case->branch->name }}</flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $case->case_code }}</flux:table.cell>
                                @foreach (CaseFileType::cases() as $type)
                                    <flux:table.cell><x-portal-file-cell :files="$case->files" :type="$type" :url="$fileUrl" /></flux:table.cell>
                                @endforeach
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="divide-y divide-zinc-100 md:hidden">
                @foreach ($cases as $case)
                    <div class="space-y-3 p-4">
                        <a href="{{ route('doctor-link.case', [$token, $case->id]) }}" class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-zinc-900">
                                    {{ $case->patient->name }}
                                    @if ($case->first_opened_at === null && $case->files->isNotEmpty())
                                        <flux:badge size="sm" color="blue" class="ms-1">جديد</flux:badge>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-sm text-zinc-500"><span dir="ltr">{{ $case->examType?->name ?? 'لم يحدد' }}</span> &middot; {{ $case->branch->name }}</div>
                            </div>
                            <div class="shrink-0 space-y-0.5 text-end text-xs whitespace-nowrap text-zinc-500">
                                <div class="ltr-nums">{{ $case->exam_date->format('d/m/Y') }}</div>
                                <div class="ltr-nums">{{ $case->case_code }}</div>
                            </div>
                        </a>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs text-zinc-500">
                            @foreach (CaseFileType::cases() as $type)
                                <div class="space-y-1.5 rounded-lg bg-zinc-50 p-2">
                                    <div class="truncate">{{ $type->shortLabel() }}</div>
                                    <x-portal-file-cell :files="$case->files" :type="$type" :url="$fileUrl" compact />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-panel>

    <p class="mt-6 text-center text-xs text-zinc-400">هذا رابط خاص بالطبيب لعرض حالاته. لا تشاركه مع أي شخص آخر.</p>
</x-layouts::public>
