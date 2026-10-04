<?php
use App\Models\{Payment, MedicalCase};
use App\Enums\Permission;
use App\Services\{FinancialReport,CasePayments};
use App\Support\{AccessScope,Money};
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\{Computed,Locked,Title};
use Livewire\Component;
use Livewire\WithPagination;
new #[Title('الحسابات')] class extends Component {
    use WithPagination;
    #[\Livewire\Attributes\Url] public string $from=''; #[\Livewire\Attributes\Url] public string $to=''; #[\Livewire\Attributes\Url] public string $branch='';
    #[Locked] public ?int $refundId=null;
    #[Locked] public string $refundRequest='';
    public string $refundAmount=''; public string $refundReason='';
    public function boot(): void { abort_unless(auth()->user()->hasPermission(Permission::ViewFinancials),403); }
    public function mount(): void { $this->from=$this->from ?: today()->toDateString(); $this->to=$this->to ?: $this->from; }
    public function filters(): array { return ['from'=>$this->from,'to'=>$this->to,'branch'=>$this->branch]; }
    public function updating(): void { $this->resetPage(); }
    #[Computed] public function summary(): array { return FinancialReport::summarize(auth()->user(),$this->filters()); }
    #[Computed] public function cases() { return FinancialReport::cases(auth()->user(),$this->filters())->with(['patient','branch','examType'])->withSum('payments','amount_minor')->latest('exam_date')->paginate(20); }
    #[Computed] public function payments() { return FinancialReport::payments(auth()->user(),$this->filters())->with(['collector','medicalCase.patient'])->latest('received_at')->limit(100)->get(); }
    public function month(): void { abort_unless(auth()->user()->isAdmin(),403); $this->from=today()->startOfMonth()->toDateString(); $this->to=today()->endOfMonth()->toDateString(); $this->resetPage(); }
    public function today(): void { $this->from=today()->toDateString(); $this->to=$this->from; $this->resetPage(); }
    public function startRefund(int $id): void { abort_unless(auth()->user()->isAdmin(),403); Payment::findOrFail($id); $this->refundId=$id; $this->refundAmount=''; $this->refundReason=''; $this->refundRequest=(string)Str::uuid(); Flux::modal('refund')->show(); }
    public function refund(): void { abort_unless(auth()->user()->isAdmin(),403); $this->validate(['refundAmount'=>['required','string'],'refundReason'=>['required','string','max:1000']]); CasePayments::refund(auth()->user(),Payment::findOrFail($this->refundId),Money::minor($this->refundAmount),$this->refundReason,$this->refundRequest); $this->refundId=null; Flux::modal('refund')->close(); Flux::toast(text:'تم تسجيل رد المبلغ مع الاحتفاظ بالحركة الأصلية.'); }
    public function export() {
        $actor=auth()->user(); $rows=FinancialReport::cases($actor,$this->filters())->with(['patient','branch','examType'])->withSum('payments','amount_minor')->get();
        return response()->streamDownload(function () use ($rows) {
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['الكود','المريض','الفرع','الفحص','التاريخ','العملة','الأساسي','الخصم','النهائي','المقبوض','المتبقي'],',','"','');
            foreach($rows as $case) { $values=[$case->case_code,$case->patient->name,$case->branch->name,$case->examType?->name,$case->exam_date->toDateString(),$case->currency,Money::decimal($case->base_price_minor),Money::decimal($case->discount_minor),Money::decimal($case->final_price_minor),Money::decimal((int)$case->payments_sum_amount_minor),$case->final_price_minor === null ? '' : Money::decimal($case->final_price_minor-(int)$case->payments_sum_amount_minor)]; $values=array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v) ? "'".$v : $v,$values); fputcsv($out,$values,',','"',''); } fclose($out);
        },'accounts-'.$this->from.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}; ?>
<div class="mx-auto w-full max-w-7xl space-y-5">
    <x-page-header title="الحسابات" :subtitle="auth()->user()->isAdmin() ? 'الفواتير والتحصيل والتقارير حسب الفرع والفترة' : 'حسابات يوم العمل الحالي للفرع المسموح به'" />
    <x-panel><div class="flex flex-wrap items-end gap-3">
        @if(auth()->user()->isAdmin())<flux:input wire:model.live="from" label="من" type="date" /><flux:input wire:model.live="to" label="إلى" type="date" /><flux:select wire:model.live="branch" label="الفرع"><flux:select.option value="">جميع الفروع</flux:select.option>@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select><flux:button wire:click="month">هذا الشهر</flux:button>@endif
        <flux:button wire:click="today">اليوم</flux:button><flux:button icon="arrow-down-tray" wire:click="export">تصدير التقرير</flux:button>
    </div></x-panel>
    @foreach($this->summary['currencies'] as $currency=>$totals)
        <div class="grid gap-3 sm:grid-cols-3"><x-stat-card :label="'المقبوض خلال الفترة — '.$currency" :value="Money::display((int)$totals['collected'])" icon="banknotes" /><x-stat-card :label="'قيمة الفحوصات — '.$currency" :value="Money::display((int)$totals['billed'])" icon="document-text" /><x-stat-card :label="'الخصومات — '.$currency" :value="Money::display((int)$totals['discount'])" icon="receipt-percent" /></div>
    @endforeach
    <p class="text-sm text-zinc-600">{{ $this->summary['cases'] }} فحص خلال الفترة · {{ $this->summary['unpriced'] }} حالة غير مسعّرة. المقبوض محسوب بتاريخ التحصيل، وقيمة الفحوصات بتاريخ الفحص.</p>
    <x-panel title="الفحوصات حسب النوع"><div class="flex flex-wrap gap-3">@foreach($this->summary['by_exam'] as $row)<flux:badge>{{ $row->examType?->name ?? 'لم يحدد' }}: {{ $row->total }}</flux:badge>@endforeach</div></x-panel>
    <x-panel title="تفاصيل الحالات" :padded="false"><div class="overflow-x-auto p-4"><table class="w-full min-w-[650px] text-start text-sm"><thead><tr class="border-b text-zinc-500">@foreach(['الحالة / المريض','الفرع / الفحص','السعر النهائي','المقبوض','المتبقي'] as $label)<th class="p-3 text-start font-medium">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($this->cases as $case)<tr class="border-b"><td class="p-3"><a class="font-medium text-brand-700" href="{{ $case->trashed() ? route('recycle-bin') : route('cases.show',$case) }}">{{ $case->case_code }}</a><div>{{ $case->patient->name }} @if($case->trashed())<small>محذوفة</small>@endif</div></td><td class="p-3">{{ $case->branch->name }}<div class="text-zinc-500">{{ $case->examType?->name ?? 'لم يحدد' }}</div></td><td class="p-3">{{ Money::display($case->final_price_minor) }} {{ $case->currency }}</td><td class="p-3">{{ Money::display((int)$case->payments_sum_amount_minor) }}</td><td class="p-3">{{ $case->final_price_minor === null ? '—' : Money::display($case->final_price_minor-(int)$case->payments_sum_amount_minor) }}</td></tr>@empty<tr><td colspan="5" class="p-6 text-center text-zinc-500">لا توجد حالات في هذه الفترة.</td></tr>@endforelse</tbody></table>{{ $this->cases->links() }}</div></x-panel>
    <x-panel title="حركات التحصيل (آخر 100 حركة)"><div class="divide-y">@forelse($this->payments as $payment)<div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><div>{{ $payment->medicalCase?->case_code }} · {{ $payment->collector?->name }}<span class="ms-2 text-zinc-500">{{ $payment->received_at->format('d/m/Y H:i') }}</span>@if($payment->reason)<p>{{ $payment->reason }}</p>@endif</div><div>{{ number_format($payment->amount_minor/100,2) }} {{ $payment->currency }} @if(auth()->user()->isAdmin() && $payment->amount_minor>0)<flux:button size="sm" variant="ghost" wire:click="startRefund({{ $payment->id }})">رد مبلغ</flux:button>@endif</div></div>@empty<p class="text-sm text-zinc-500">لا توجد حركات.</p>@endforelse</div></x-panel>
    <flux:modal name="refund" class="max-w-md"><form wire:submit="refund" class="space-y-4"><flux:heading>رد مبلغ</flux:heading><flux:input wire:model="refundAmount" label="المبلغ" inputmode="decimal" /><flux:textarea wire:model="refundReason" label="السبب" rows="3" /><flux:error name="price" /><flux:button type="submit" variant="primary">تسجيل رد المبلغ</flux:button></form></flux:modal>
</div>
