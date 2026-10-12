<?php
use App\Models\{Expense,Payment,Setting};
use App\Enums\Permission;
use App\Services\{FinancialReport,CasePayments};
use App\Support\{AccessScope,Money};
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\{Computed,Locked,Title,Url};
use Livewire\Component;
use Livewire\WithPagination;
new #[Title('الحسابات')] class extends Component {
    use WithPagination;
    #[Url] public string $from=''; #[Url] public string $to=''; #[Url] public string $branch=''; #[Url] public string $preset='today';
    #[Url(as:'q')] public string $search=''; #[Url] public bool $dueOnly=false;
    #[Locked] public ?int $refundId=null;
    #[Locked] public string $refundRequest='';
    public string $refundAmount=''; public string $refundReason='';
    public function boot(): void { abort_unless(auth()->user()->hasPermission(Permission::ViewFinancials),403); }
    public function mount(): void { if(! $this->full()){ $this->preset='today'; } $this->applyPreset($this->preset ?: 'today', false); }
    private function full(): bool { return auth()->user()->hasPermission(Permission::ViewAllFinancials); }
    public function filters(): array { return ['from'=>$this->from,'to'=>$this->to,'branch'=>$this->branch]; }
    public function updating($name): void { if(! in_array($name,['refundAmount','refundReason'],true)) $this->resetPage(); }
    public function updatedFrom(): void { $this->preset='custom'; } public function updatedTo(): void { $this->preset='custom'; }
    public function applyPreset(string $preset, bool $reset=true): void {
        if($preset!=='today'){ abort_unless($this->full(),403); }
        $t=today();
        [$f,$e]=match($preset){ 'yesterday'=>[$t->copy()->subDay(),$t->copy()->subDay()], 'week'=>[$t->copy()->startOfWeek(Carbon::SATURDAY),$t], 'month'=>[$t->copy()->startOfMonth(),$t], 'last_month'=>[$t->copy()->subMonthNoOverflow()->startOfMonth(),$t->copy()->subMonthNoOverflow()->endOfMonth()], 'year'=>[$t->copy()->startOfYear(),$t], 'custom'=>[Carbon::parse($this->from ?: $t),Carbon::parse($this->to ?: $t)], default=>[$t,$t] };
        $this->preset=$preset; $this->from=$f->toDateString(); $this->to=$e->toDateString();
        if($reset){ $this->resetPage(); }
    }
    #[Computed] public function summary(): array { return FinancialReport::summarize(auth()->user(),$this->filters()); }
    #[Computed] public function currency(): string { return (string)(array_key_first($this->summary['currencies']) ?? Setting::get('currency') ?? ''); }
    #[Computed] public function totals(): array { return $this->summary['currencies'][$this->currency] ?? ['collected'=>0,'billed'=>0,'outstanding'=>0]; }
    #[Computed] public function expenses(): ?int {
        if(! auth()->user()->hasPermission(Permission::ManageExpenses) && ! $this->full()){ return null; }
        return (int) Expense::when(! auth()->user()->isAdmin(),fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))->when($this->branch,fn($q)=>$q->where('branch_id',$this->branch))->whereDate('spent_on','>=',$this->from)->whereDate('spent_on','<=',$this->to)->sum('amount_minor');
    }
    /** Day-by-day collected vs exam value for the chart. */
    #[Computed] public function series(): array {
        $days=Carbon::parse($this->from)->diffInDays(Carbon::parse($this->to))+1;
        if($days<2 || $days>92){ return []; }
        $billed=FinancialReport::cases(auth()->user(),$this->filters())->selectRaw('DATE(exam_date) as d, SUM(final_price_minor) as v')->groupBy(DB::raw('DATE(exam_date)'))->pluck('v','d');
        $paid=FinancialReport::payments(auth()->user(),$this->filters())->selectRaw('DATE(received_at) as d, SUM(amount_minor) as v')->groupBy(DB::raw('DATE(received_at)'))->pluck('v','d');
        $out=[]; for($d=Carbon::parse($this->from); $d->lte(Carbon::parse($this->to)); $d->addDay()){ $k=$d->toDateString(); $out[]=['d'=>$d->format('d/m'),'billed'=>(int)($billed[$k] ?? 0),'paid'=>(int)($paid[$k] ?? 0)]; }
        return $out;
    }
    #[Computed] public function breakdowns(): array {
        $base=fn()=>FinancialReport::cases(auth()->user(),$this->filters())->whereNotNull('final_price_minor');
        $paidSub='(SELECT COALESCE(SUM(amount_minor),0) FROM payments WHERE payments.medical_case_id = medical_cases.id)';
        $group=function(string $col) use($base,$paidSub){ return $base()->selectRaw("$col as k, COUNT(*) as n, SUM(final_price_minor) as billed, SUM($paidSub) as paid")->groupBy($col)->orderByDesc('billed')->get(); };
        $methods=FinancialReport::payments(auth()->user(),$this->filters())->selectRaw('method, SUM(amount_minor) as v')->groupBy('method')->pluck('v','method');
        return [
            'branch'=>$group('branch_id')->map(fn($r)=>$r->setAttribute('label',\App\Models\Branch::withTrashed()->find($r->k)?->name ?? '—')),
            'exam'=>$group('exam_type_id')->map(fn($r)=>$r->setAttribute('label',\App\Models\ExamType::find($r->k)?->name ?? 'لم يحدد')),
            'doctor'=>$group('doctor_id')->take(10)->map(fn($r)=>$r->setAttribute('label',$r->k ? (\App\Models\Doctor::withTrashed()->with('user')->find($r->k)?->display_name ?? '—') : 'بدون طبيب')),
            'methods'=>$methods,
        ];
    }
    #[Computed] public function cases() {
        return FinancialReport::cases(auth()->user(),$this->filters())->with(['patient','branch','examType','doctor.user'])->withSum('payments','amount_minor')
            ->when(trim($this->search)!=='',fn($q)=>$q->where(fn($w)=>$w->where('case_code','like','%'.trim($this->search).'%')->orWhereHas('patient',fn($p)=>$p->withoutGlobalScope('access')->search(trim($this->search)))))
            ->when($this->dueOnly,fn($q)=>$q->whereNotNull('final_price_minor')->whereRaw('final_price_minor > (SELECT COALESCE(SUM(amount_minor),0) FROM payments WHERE payments.medical_case_id = medical_cases.id)'))
            ->latest('exam_date')->latest('id')->paginate(20);
    }
    #[Computed] public function payments() { return FinancialReport::payments(auth()->user(),$this->filters())->with(['collector','medicalCase.patient'])->latest('received_at')->limit(100)->get(); }
    public function startRefund(int $id): void { abort_unless($this->full(),403); Payment::findOrFail($id); $this->refundId=$id; $this->refundAmount=''; $this->refundReason=''; $this->refundRequest=(string)Str::uuid(); Flux::modal('refund')->show(); }
    public function refund(): void { abort_unless($this->full(),403); $this->validate(['refundAmount'=>['required','string'],'refundReason'=>['required','string','max:1000']]); CasePayments::refund(auth()->user(),Payment::findOrFail($this->refundId),Money::minor($this->refundAmount),$this->refundReason,$this->refundRequest); $this->refundId=null; Flux::modal('refund')->close(); Flux::toast(text:'تم تسجيل رد المبلغ مع الاحتفاظ بالحركة الأصلية.'); }
    public function export() {
        $actor=auth()->user(); $rows=FinancialReport::cases($actor,$this->filters())->with(['patient','branch','examType','doctor.user'])->withSum('payments','amount_minor')->get();
        return response()->streamDownload(function () use ($rows) {
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['الكود','المريض','الموبايل','الطبيب','الفرع','الفحص','التاريخ','العملة','قيمة الفحص','المقبوض','المتبقي'],',','"','');
            foreach($rows as $case) { $values=[$case->case_code,$case->patient?->name,$case->patient?->phone,$case->doctor?->display_name,$case->branch?->name,$case->examType?->name,$case->exam_date->toDateString(),$case->currency,Money::decimal($case->final_price_minor),Money::decimal((int)$case->payments_sum_amount_minor),$case->final_price_minor === null ? '' : Money::decimal($case->final_price_minor-(int)$case->payments_sum_amount_minor)]; $values=array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v) ? "'".$v : $v,$values); fputcsv($out,$values,',','"',''); } fclose($out);
        },'accounts-'.$this->from.'_'.$this->to.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}; ?>
<div class="mx-auto w-full max-w-7xl space-y-5">
    @php($t = $this->totals)
    @php($exp = $this->expenses)
    @php($cur = $this->currency)
    <x-page-header title="الحسابات" :subtitle="$this->full() ? 'التحصيل والمتبقي وقيمة الفحوصات والمصروفات حسب الفترة والفرع' : 'حسابات اليوم للفرع'">
        <x-slot:actions>
            <flux:button icon="printer" :href="route('accounts.print', $this->filters())" target="_blank">طباعة</flux:button>
            <flux:button icon="arrow-down-tray" wire:click="export">Excel</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel>
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-1 rounded-lg bg-zinc-100 p-1">
                @foreach(['today'=>'اليوم','yesterday'=>'أمس','week'=>'الأسبوع','month'=>'هذا الشهر','last_month'=>'الشهر الماضي','year'=>'السنة'] as $k=>$l)
                    @if($k==='today' || $this->full())
                        <button type="button" wire:click="applyPreset('{{ $k }}')" class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $preset===$k ? 'bg-white text-brand-700 shadow-sm' : 'text-zinc-600 hover:text-zinc-900' }}">{{ $l }}</button>
                    @endif
                @endforeach
            </div>
            @if($this->full())
                <flux:input wire:model.live="from" label="من" type="date" class="max-w-40" />
                <flux:input wire:model.live="to" label="إلى" type="date" class="max-w-40" />
            @endif
            @if(auth()->user()->isAdmin() || count(AccessScope::branchIds(auth()->user()))>1)
                <flux:select wire:model.live="branch" label="الفرع" class="max-w-48"><flux:select.option value="">جميع الفروع</flux:select.option>@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select>
            @endif
        </div>
    </x-panel>

    {{-- KPIs --}}
    <div class="grid gap-3 sm:grid-cols-2 {{ $exp !== null ? 'xl:grid-cols-5' : 'xl:grid-cols-3' }}">
        <div class="relative overflow-hidden rounded-xl bg-brand-700 p-5 text-white shadow-[0_18px_40px_-24px_rgb(0_80_170/0.8)]">
            <div class="flex items-center justify-between text-sm text-white/80">المقبوض<flux:icon.banknotes variant="mini" class="text-phosphor" /></div>
            <div class="mt-2 text-3xl font-bold ltr-nums">{{ Money::display((int)$t['collected']) }}</div>
            <div class="mt-1 text-xs text-white/70">{{ $cur }} · بتاريخ التحصيل</div>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex items-center justify-between text-sm text-amber-800">المتبقي<flux:icon.clock variant="mini" /></div>
            <div class="mt-2 text-3xl font-bold text-amber-900 ltr-nums">{{ Money::display((int)($t['outstanding'] ?? 0)) }}</div>
            <div class="mt-1 text-xs text-amber-700">على فحوصات الفترة</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5">
            <div class="flex items-center justify-between text-sm text-zinc-500">قيمة الفحوصات<flux:icon.document-text variant="mini" /></div>
            <div class="mt-2 text-3xl font-bold ltr-nums">{{ Money::display((int)$t['billed']) }}</div>
            <div class="mt-1 text-xs text-zinc-500"><span class="ltr-nums">{{ $this->summary['cases'] }}</span> فحص @if($this->summary['unpriced'])· <span class="text-amber-700"><span class="ltr-nums">{{ $this->summary['unpriced'] }}</span> بدون سعر</span>@endif</div>
        </div>
        @if($exp !== null)
            <div class="rounded-xl border border-zinc-200 bg-white p-5">
                <div class="flex items-center justify-between text-sm text-zinc-500">المصروفات<flux:icon.receipt-percent variant="mini" /></div>
                <div class="mt-2 text-3xl font-bold ltr-nums {{ $exp > 0 ? 'text-red-700' : 'text-zinc-900' }}">{{ Money::display($exp) }}</div>
                @if(auth()->user()->hasPermission(Permission::ManageExpenses))<a href="{{ route('expenses.index') }}" wire:navigate class="mt-1 inline-block text-xs text-brand-700 underline">إدارة المصروفات</a>@endif
            </div>
            @php($net = (int)$t['collected'] - $exp)
            <div class="rounded-xl border-2 {{ $net >= 0 ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }} p-5">
                <div class="flex items-center justify-between text-sm {{ $net >= 0 ? 'text-green-800' : 'text-red-800' }}">الصافي<flux:icon.scale variant="mini" /></div>
                <div class="mt-2 text-3xl font-bold ltr-nums {{ $net >= 0 ? 'text-green-800' : 'text-red-800' }}">{{ Money::display($net) }}</div>
                <div class="mt-1 text-xs {{ $net >= 0 ? 'text-green-700' : 'text-red-700' }}">المقبوض − المصروفات</div>
            </div>
        @endif
    </div>

    {{-- Daily chart --}}
    @if(count($this->series))
        @php($max = max(1, collect($this->series)->flatMap(fn($r)=>[$r['billed'],$r['paid']])->max()))
        <x-panel title="الحركة اليومية" icon="chart-bar">
            <div class="mb-3 flex gap-4 text-xs text-zinc-600"><span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-600"></span>المقبوض</span><span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-200"></span>قيمة الفحوصات</span></div>
            <div class="flex h-48 items-end gap-1 overflow-x-auto" dir="ltr">
                @foreach($this->series as $r)
                    <div class="group relative flex h-full min-w-4 flex-1 flex-col items-center justify-end">
                        <div class="flex h-full w-full items-end justify-center gap-px">
                            <div class="w-1/2 max-w-3 rounded-t bg-brand-200" style="height: {{ round($r['billed'] * 100 / $max, 1) }}%"></div>
                            <div class="w-1/2 max-w-3 rounded-t bg-brand-600" style="height: {{ round($r['paid'] * 100 / $max, 1) }}%"></div>
                        </div>
                        <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-zinc-900 px-2 py-1 text-xs text-white group-hover:block" dir="rtl">{{ $r['d'] }} · مقبوض {{ Money::display($r['paid']) }} · فحوصات {{ Money::display($r['billed']) }}</div>
                        @if(count($this->series) <= 31)<div class="mt-1 text-[0.6rem] text-zinc-400">{{ substr($r['d'],0,2) }}</div>@endif
                    </div>
                @endforeach
            </div>
        </x-panel>
    @endif

    {{-- Breakdowns --}}
    @php($b = $this->breakdowns)
    <div class="grid gap-5 lg:grid-cols-3">
        @foreach(['doctor'=>['حسب الطبيب (أعلى 10)','academic-cap'],'exam'=>['حسب نوع الفحص','queue-list'],'branch'=>['حسب الفرع','building-office-2']] as $key=>[$title,$icon])
            <x-panel :title="$title" :icon="$icon">
                @php($top = max(1, (int) $b[$key]->max('billed')))
                <div class="space-y-3">
                @forelse($b[$key] as $row)
                    <div>
                        <div class="flex items-baseline justify-between gap-2 text-sm"><span class="truncate">{{ $row->label }} <span class="text-xs text-zinc-400 ltr-nums">({{ $row->n }})</span></span><span class="font-semibold ltr-nums">{{ Money::display((int)$row->billed) }}</span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-zinc-100"><div class="h-1.5 rounded-full bg-brand-600" style="width: {{ round($row->billed * 100 / $top) }}%"></div></div>
                        @if((int)$row->billed > (int)$row->paid)<div class="mt-0.5 text-xs text-amber-700">متبقي <span class="ltr-nums">{{ Money::display((int)$row->billed - (int)$row->paid) }}</span></div>@endif
                    </div>
                @empty<p class="text-sm text-zinc-500">لا توجد بيانات.</p>@endforelse
                </div>
            </x-panel>
        @endforeach
    </div>
    @if($b['methods']->isNotEmpty())
        <div class="flex flex-wrap gap-3">@foreach($b['methods'] as $method=>$v)<div class="rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm"><span class="text-zinc-500">{{ ['cash'=>'نقدًا','card'=>'بطاقة','transfer'=>'تحويل'][$method] ?? $method }}:</span> <span class="font-semibold ltr-nums">{{ Money::display((int)$v) }}</span></div>@endforeach</div>
    @endif

    {{-- Cases --}}
    <x-panel title="الفحوصات" :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="ابحث بالكود أو اسم المريض أو الموبايل" class="max-w-sm flex-1" clearable />
            <flux:switch wire:model.live="dueOnly" label="عليها متبقي فقط" />
        </div>
        <div class="overflow-x-auto p-4">
            <table class="w-full min-w-[760px] text-sm">
                <thead><tr class="border-b text-zinc-500">@foreach(['الحالة / المريض','الطبيب','الفرع / الفحص','قيمة الفحص','المقبوض','المتبقي',''] as $label)<th class="p-3 text-start font-medium">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@forelse($this->cases as $case)
                    @php($due = $case->final_price_minor === null ? null : $case->final_price_minor-(int)$case->payments_sum_amount_minor)
                    <tr class="border-b" wire:key="ac-{{ $case->id }}">
                        <td class="p-3"><a class="font-medium text-brand-700 ltr-nums" href="{{ $case->trashed() ? route('recycle-bin') : route('cases.show',$case) }}" wire:navigate>{{ $case->case_code }}</a><div>{{ $case->patient?->name }} @if($case->trashed())<flux:badge size="sm" color="zinc">محذوفة</flux:badge>@endif</div></td>
                        <td class="p-3">{{ $case->doctor?->display_name ?? '—' }}</td>
                        <td class="p-3">{{ $case->branch?->name }}<div class="text-zinc-500">{{ $case->examType?->name ?? 'لم يحدد' }}</div></td>
                        <td class="p-3 ltr-nums">{{ Money::display($case->final_price_minor) }}</td>
                        <td class="p-3 ltr-nums text-green-700">{{ Money::display((int)$case->payments_sum_amount_minor) }}</td>
                        <td class="p-3 ltr-nums font-semibold {{ $due > 0 ? 'text-amber-700' : 'text-zinc-400' }}">{{ $due === null ? 'بدون سعر' : Money::display($due) }}</td>
                        <td class="p-3 text-end">@unless($case->trashed())<flux:button size="sm" variant="ghost" icon="printer" :href="route('cases.receipt',$case)" target="_blank" aria-label="طباعة إيصال" />@endunless</td>
                    </tr>
                @empty<tr><td colspan="7" class="p-6 text-center text-zinc-500">لا توجد فحوصات في هذه الفترة.</td></tr>@endforelse</tbody>
            </table>
            {{ $this->cases->links() }}
        </div>
    </x-panel>

    <x-panel title="حركات التحصيل (آخر 100)" icon="arrows-right-left"><div class="divide-y">@forelse($this->payments as $payment)<div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><div><span class="ltr-nums font-medium">{{ $payment->medicalCase?->case_code }}</span> · {{ $payment->medicalCase?->patient?->name }} · {{ $payment->collector?->name }}<span class="ms-2 text-zinc-500 ltr-nums">{{ $payment->received_at->format('d/m/Y H:i') }}</span>@if($payment->reason)<p class="text-zinc-600">{{ $payment->reason }}</p>@endif</div><div class="flex items-center gap-2"><span class="font-semibold ltr-nums {{ $payment->amount_minor < 0 ? 'text-red-600' : '' }}">{{ Money::display($payment->amount_minor) }} {{ $payment->currency }}</span>@if($this->full() && $payment->amount_minor>0)<flux:button size="sm" variant="ghost" wire:click="startRefund({{ $payment->id }})">رد مبلغ</flux:button>@endif</div></div>@empty<p class="text-sm text-zinc-500">لا توجد حركات.</p>@endforelse</div></x-panel>

    <flux:modal name="refund" class="max-w-md"><form wire:submit="refund" class="space-y-4"><flux:heading>رد مبلغ</flux:heading><flux:input wire:model="refundAmount" label="المبلغ" inputmode="decimal" /><flux:textarea wire:model="refundReason" label="السبب" rows="3" /><flux:error name="price" /><flux:button type="submit" variant="primary">تسجيل رد المبلغ</flux:button></form></flux:modal>
</div>
