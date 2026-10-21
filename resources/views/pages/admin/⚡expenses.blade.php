<?php
use App\Enums\Permission;
use App\Models\{Expense,Setting,User};
use App\Support\{AccessScope,ActivityLogger,Money};
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Computed,Locked,Title,Url};
use Livewire\Component;
use Livewire\WithPagination;
new #[Title('المصروفات والرواتب')] class extends Component {
    use WithPagination;
    #[Url] public string $month=''; #[Url] public string $branch=''; #[Url] public string $category='';
    #[Locked] public ?int $editingId=null;
    public array $form=['category'=>'other','amount'=>'','spent_on'=>'','branch_id'=>'','note'=>''];
    /** @var array<int,string> shifts typed per user for the salary run */
    public array $shifts=[];
    public function boot(): void { abort_unless(auth()->user()->hasPermission(Permission::ManageExpenses),403); }
    public function mount(): void { $this->month = preg_match('/^\d{4}-\d{2}$/',$this->month) ? $this->month : today()->format('Y-m'); }
    public function updating(): void { $this->resetPage(); }
    private function range(): array { $start=\Carbon\Carbon::createFromFormat('Y-m-d',$this->month.'-01')->startOfMonth(); return [$start->toDateString(),$start->copy()->endOfMonth()->toDateString()]; }
    private function scoped() {
        [$from,$to]=$this->range();
        return Expense::query()->with(['branch','user'])
            ->when(! auth()->user()->isAdmin(), fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))
            ->whereDate('spent_on','>=',$from)->whereDate('spent_on','<=',$to)
            ->when($this->branch, fn($q,$b)=>$q->where('branch_id',$b));
    }
    #[Computed] public function rows() { return $this->scoped()->when($this->category, fn($q,$c)=>$q->where('category',$c))->latest('spent_on')->latest('id')->paginate(25); }
    #[Computed] public function byCategory() { return $this->scoped()->selectRaw('category, SUM(amount_minor) as total, COUNT(*) as n')->groupBy('category')->orderByDesc('total')->get(); }
    #[Computed] public function total(): int { return (int) $this->scoped()->sum('amount_minor'); }
    #[Computed] public function staff() {
        return User::whereNotNull('shift_rate_minor')->where('shift_rate_minor','>',0)->where('is_active',true)
            ->when(! auth()->user()->isAdmin(), fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))
            ->when($this->branch, fn($q,$b)=>$q->where('branch_id',$b))->orderBy('name')->get();
    }
    #[Computed] public function paidThisMonth(): array { return $this->scoped()->where('category','salaries')->whereNotNull('user_id')->selectRaw('user_id, SUM(amount_minor) as total, SUM(shifts) as shifts')->groupBy('user_id')->get()->keyBy('user_id')->all(); }
    public function create(): void { $this->editingId=null; $this->form=['category'=>'other','amount'=>'','spent_on'=>today()->toDateString(),'branch_id'=>(string)(auth()->user()->branch_id ?? ''),'note'=>'']; $this->resetValidation(); Flux::modal('expense-form')->show(); }
    public function edit(int $id): void {
        $e=$this->scoped()->findOrFail($id);
        $this->editingId=$e->id; $this->form=['category'=>$e->category,'amount'=>Money::decimal($e->amount_minor),'spent_on'=>$e->spent_on->toDateString(),'branch_id'=>(string)$e->branch_id,'note'=>(string)$e->note];
        $this->resetValidation(); Flux::modal('expense-form')->show();
    }
    private function currency(): string { $c=Setting::get('currency'); abort_unless(is_string($c) && $c!=='',422,'حدد العملة من إعدادات المركز أولًا.'); return $c; }
    private function allowedBranch(?string $id): ?int {
        if(! $id){ abort_unless(auth()->user()->isAdmin(),422); return null; }
        abort_unless(AccessScope::branches(auth()->user())->whereKey($id)->exists(),403);
        return (int)$id;
    }
    public function save(): void {
        $data=$this->validate(['form.category'=>['required',Rule::in(array_keys(Expense::CATEGORIES))],'form.amount'=>['required','numeric','gt:0','max:100000000'],'form.spent_on'=>['required','date_format:Y-m-d'],'form.branch_id'=>[auth()->user()->isAdmin() ? 'nullable' : 'required'],'form.note'=>['nullable','string','max:500']],[],['form.amount'=>'المبلغ','form.spent_on'=>'التاريخ','form.branch_id'=>'الفرع'])['form'];
        $values=['category'=>$data['category'],'amount_minor'=>Money::minor((string)$data['amount']),'spent_on'=>$data['spent_on'],'branch_id'=>$this->allowedBranch($data['branch_id'] ?: null),'note'=>trim((string)$data['note']) ?: null];
        $expense=$this->editingId ? $this->scoped()->findOrFail($this->editingId) : new Expense(['currency'=>$this->currency(),'created_by'=>auth()->id()]);
        $expense->fill($values)->save();
        ActivityLogger::log('expense.saved',$expense,['category'=>$expense->category,'amount'=>$expense->amount_minor]);
        Flux::modal('expense-form')->close(); Flux::toast(variant:'success',text:'تم حفظ المصروف.'); $this->clear();
    }
    public function delete(int $id): void {
        $e=$this->scoped()->findOrFail($id);
        ActivityLogger::log('expense.deleted',$e,['category'=>$e->category,'amount'=>$e->amount_minor]); $e->delete();
        $this->clear(); Flux::toast(text:'تم حذف المصروف.');
    }
    /** Record a salary for one employee: shifts × their shift rate. */
    public function paySalary(int $userId): void {
        $user=$this->staff->firstWhere('id',$userId); abort_unless($user,404);
        $shifts=(int)($this->shifts[$userId] ?? 0);
        if($shifts<1 || $shifts>62){ $this->addError('shifts.'.$userId,'اكتب عدد الشيفتات (1–62).'); return; }
        [, $to]=$this->range();
        $expense=Expense::create(['category'=>'salaries','amount_minor'=>$shifts*$user->shift_rate_minor,'currency'=>$this->currency(),'spent_on'=>min($to,today()->toDateString()),'branch_id'=>$user->branch_id,'user_id'=>$user->id,'shifts'=>$shifts,'note'=>'راتب '.$user->name.' — '.$shifts.' شيفت','created_by'=>auth()->id()]);
        ActivityLogger::log('expense.saved',$expense,['category'=>'salaries','amount'=>$expense->amount_minor,'user'=>$user->name]);
        unset($this->shifts[$userId]); $this->clear(); Flux::toast(variant:'success',text:'تم تسجيل راتب '.$user->name.': '.Money::display($expense->amount_minor).'.');
    }
    private function clear(): void { unset($this->rows,$this->byCategory,$this->total,$this->paidThisMonth); }
}; ?>
<div class="mx-auto w-full max-w-6xl space-y-5">
    <x-page-header eyebrow="الحسابات" title="المصروفات والرواتب" subtitle="كل ما يُصرف من المركز، ورواتب الموظفين بعدد الشيفتات">
        <x-slot:actions><flux:button icon="table-cells" :href="route('exports.expenses', ['month' => $month, 'branch' => $branch ?: null])">تصدير الشهر Excel</flux:button><flux:button variant="primary" icon="plus" wire:click="create">إضافة مصروف</flux:button></x-slot:actions>
    </x-page-header>

    <x-panel><div class="flex flex-wrap items-end gap-3">
        <flux:input type="month" wire:model.live="month" label="الشهر" class="max-w-48" />
        <flux:select wire:model.live="branch" label="الفرع" class="max-w-56"><flux:select.option value="">{{ auth()->user()->isAdmin() ? 'جميع الفروع' : 'فروعي' }}</flux:select.option>@foreach(AccessScope::branches(auth()->user())->orderBy('name')->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select>
        <flux:select wire:model.live="category" label="البند" class="max-w-56"><flux:select.option value="">كل البنود</flux:select.option>@foreach(Expense::CATEGORIES as $key=>$label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@endforeach</flux:select>
    </div></x-panel>

    <div class="grid gap-3 lg:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5">
            <div class="text-sm text-zinc-500">إجمالي مصروفات الشهر</div>
            <div class="mt-2 text-3xl font-bold ltr-nums">{{ Money::display($this->total) }} <span class="text-base font-medium text-zinc-500">{{ Setting::get('currency') }}</span></div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 lg:col-span-2">
            <div class="mb-3 text-sm text-zinc-500">حسب البند</div>
            @forelse($this->byCategory as $row)
                @php($pct = $this->total ? round($row->total * 100 / $this->total) : 0)
                <div class="mb-2 grid grid-cols-[8rem_1fr_auto] items-center gap-3 text-sm">
                    <span class="truncate">{{ Expense::CATEGORIES[$row->category] ?? $row->category }}</span>
                    <div class="h-2 rounded-full bg-zinc-100"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $pct }}%"></div></div>
                    <span class="ltr-nums font-medium">{{ Money::display((int)$row->total) }}</span>
                </div>
            @empty<p class="text-sm text-zinc-500">لا توجد مصروفات في هذا الشهر.</p>@endforelse
        </div>
    </div>

    <x-panel title="الرواتب بالشيفت" icon="users">
        @if($this->staff->isEmpty())
            <p class="text-sm text-zinc-600">لا يوجد موظفون لهم أجر شيفت. حدد «أجر الشيفت» لكل موظف من <a class="text-brand-700 underline" href="{{ route('users.index') }}" wire:navigate>المستخدمون</a>.</p>
        @else
            <div class="divide-y">
                @foreach($this->staff as $u)
                    @php($paid = $this->paidThisMonth[$u->id] ?? null)
                    <div class="flex flex-wrap items-center gap-3 py-3" wire:key="s-{{ $u->id }}">
                        <div class="min-w-40 flex-1"><div class="font-medium">{{ $u->name }}</div><div class="text-sm text-zinc-500">{{ $u->role->label() }} · الشيفت <span class="ltr-nums">{{ Money::display($u->shift_rate_minor) }}</span></div></div>
                        @if($paid)<flux:badge color="green" size="sm">صُرف هذا الشهر: {{ Money::display((int)$paid->total) }} ({{ (int)$paid->shifts }} شيفت)</flux:badge>@endif
                        <div class="flex items-start gap-2">
                            <div class="w-28"><flux:input wire:model.live.debounce.300ms="shifts.{{ $u->id }}" type="number" min="1" max="62" placeholder="عدد الشيفتات" size="sm" /><flux:error name="shifts.{{ $u->id }}" /></div>
                            <div class="min-w-24 pt-2 text-sm font-semibold ltr-nums">= {{ Money::display(((int)($shifts[$u->id] ?? 0)) * $u->shift_rate_minor) }}</div>
                            <flux:button size="sm" variant="primary" wire:click="paySalary({{ $u->id }})" wire:confirm="تسجيل راتب {{ $u->name }} كمصروف؟">تسجيل الراتب</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-panel>

    <x-panel title="سجل المصروفات" :padded="false"><div class="overflow-x-auto p-4">
        <table class="w-full min-w-[640px] text-sm"><thead><tr class="border-b text-zinc-500">@foreach(['التاريخ','البند','الفرع','ملاحظة','المبلغ',''] as $h)<th class="p-3 text-start font-medium">{{ $h }}</th>@endforeach</tr></thead>
        <tbody>@forelse($this->rows as $e)<tr class="border-b" wire:key="e-{{ $e->id }}">
            <td class="p-3 ltr-nums">{{ $e->spent_on->format('d/m/Y') }}</td>
            <td class="p-3"><flux:badge size="sm" :color="$e->category==='salaries' ? 'blue' : 'zinc'">{{ $e->categoryLabel() }}</flux:badge></td>
            <td class="p-3">{{ $e->branch?->name ?? 'عام' }}</td>
            <td class="p-3 text-zinc-600">{{ $e->note }}</td>
            <td class="p-3 font-semibold ltr-nums">{{ Money::display($e->amount_minor) }}</td>
            <td class="p-3 text-end whitespace-nowrap"><flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $e->id }})" aria-label="تعديل" /><flux:button size="sm" variant="ghost" icon="trash" class="text-red-600!" wire:click="delete({{ $e->id }})" wire:confirm="حذف هذا المصروف؟" aria-label="حذف" /></td>
        </tr>@empty<tr><td colspan="6" class="p-6 text-center text-zinc-500">لا توجد مصروفات.</td></tr>@endforelse</tbody></table>
        {{ $this->rows->links() }}
    </div></x-panel>

    <flux:modal name="expense-form" class="w-full max-w-lg"><form wire:submit="save" class="space-y-4">
        <flux:heading size="lg">{{ $editingId ? 'تعديل مصروف' : 'إضافة مصروف' }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="form.category" label="البند">@foreach(Expense::CATEGORIES as $key=>$label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@endforeach</flux:select>
            <flux:input wire:model="form.amount" label="المبلغ" inputmode="decimal" />
            <flux:input wire:model="form.spent_on" label="التاريخ" type="date" />
            <flux:select wire:model="form.branch_id" label="الفرع">@if(auth()->user()->isAdmin())<flux:select.option value="">عام (كل الفروع)</flux:select.option>@else<flux:select.option value="">اختر الفرع</flux:select.option>@endif @foreach(AccessScope::branches(auth()->user())->orderBy('name')->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select>
        </div>
        <flux:textarea wire:model="form.note" label="ملاحظة" rows="2" />
        <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">إلغاء</flux:button></flux:modal.close><flux:button type="submit" variant="primary">حفظ</flux:button></div>
    </form></flux:modal>
</div>
