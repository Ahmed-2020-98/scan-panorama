<?php
use App\Models\{BookletRequest,DoctorVisit,User};
use App\Services\{CenterAlerts,DoctorPerformance};
use App\Support\{AccessScope,ActivityLogger,Phone};
use App\Enums\Permission;
use Flux\Flux;
use Livewire\Attributes\{Computed,Locked,Title,Url};
use Livewire\Component;
use Livewire\WithPagination;
new #[Title('زيارات الأطباء')] class extends Component {
    use WithPagination;
    #[Locked] public ?int $editingId=null;
    #[Url] public string $branch=''; #[Url] public string $status=''; #[Url(as:'q')] public string $search=''; #[Url] public string $doctor='';
    public array $form=['doctor_id'=>'','branch_id'=>'','responsible_user_id'=>'','visited_at'=>'','next_visit_at'=>'','comment'=>'','agreement'=>'','notes'=>''];
    public function boot(): void { abort_unless(auth()->user()->hasPermission(Permission::ManageVisits),403); }
    public function mount(): void { CenterAlerts::runDaily(); }
    public function updating($name): void { if($name!=='form' && ! str_starts_with($name,'form.')) $this->resetPage(); }
    private function query() { return DoctorVisit::query()->when(!auth()->user()->isAdmin(),fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))->when($this->branch,fn($q)=>$q->where('branch_id',$this->branch)); }
    #[Computed] public function performance() { return DoctorPerformance::rows(auth()->user(), $this->branch ? (int)$this->branch : null); }
    #[Computed] public function rows() {
        $term=mb_strtolower(trim($this->search));
        return $this->performance
            ->when($this->doctor, fn($c)=>$c->where('doctor.id',(int)$this->doctor))
            ->when($this->status==='overdue', fn($c)=>$c->filter(fn($r)=>$r['next_visit'] && $r['next_visit']->lte(today())))
            ->when($this->status==='never', fn($c)=>$c->filter(fn($r)=>! $r['last_visit']))
            ->when($this->status && ! in_array($this->status,['overdue','never'],true), fn($c)=>$c->where('status',$this->status))
            ->when($term!=='', fn($c)=>$c->filter(fn($r)=>str_contains(mb_strtolower($r['doctor']->display_name.' '.$r['doctor']->code),$term)))
            ->sortBy(fn($r)=>[array_search($r['status'],array_keys(DoctorPerformance::STATUSES)), -$r['last30']])->values();
    }
    #[Computed] public function summary(): array {
        $p=$this->performance; $month=today()->startOfMonth()->toDateString();
        return [
            'doctors'=>$p->count(),
            'alerts'=>$p->whereIn('status',['stopped','declining'])->count(),
            'overdue'=>$p->filter(fn($r)=>$r['next_visit'] && $r['next_visit']->lte(today()))->count(),
            'visits'=>(clone $this->query())->whereDate('visited_at','>=',$month)->count(),
            'cases'=>$p->sum('last30'),
        ];
    }
    #[Computed] public function upcoming() { return $this->query()->with(['doctor.user','responsible'])->pendingFollowup()->whereDate('next_visit_at','<=',today()->addDays(14))->orderBy('next_visit_at')->limit(30)->get(); }
    #[Computed] public function visits() { return $this->query()->with(['doctor.user','branch','responsible'])->when($this->doctor,fn($q)=>$q->where('doctor_id',$this->doctor))->latest('visited_at')->latest('id')->paginate(15); }
    #[Computed] public function booklets() { return BookletRequest::with(['doctor.user','branch','requester'])->where('status','requested')->when(! auth()->user()->isAdmin(),fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))->latest()->get(); }
    #[Computed] public function formDoctors() { return AccessScope::doctors(auth()->user())->active()->with('user')->when($this->form['branch_id'],fn($q)=>$q->whereHas('branches',fn($b)=>$b->where('branches.id',$this->form['branch_id'])))->orderByName()->get(); }
    public function create(?int $doctorId=null): void {
        $this->reset('editingId','form'); $this->resetValidation();
        $this->form['branch_id']=(string)($this->branch ?: (auth()->user()->branch_id ?? AccessScope::branches(auth()->user())->value('id')));
        $this->form['responsible_user_id']=(string)auth()->id(); $this->form['visited_at']=today()->toDateString(); $this->form['next_visit_at']=today()->addDays(30)->toDateString();
        if($doctorId){ $this->form['doctor_id']=(string)$doctorId; }
        unset($this->formDoctors); Flux::modal('visit-form')->show();
    }
    public function edit(int $id): void { $row=$this->query()->findOrFail($id); $this->editingId=$id; foreach(array_keys($this->form) as $key){$this->form[$key]=in_array($key,['visited_at','next_visit_at'],true) ? ($row->{$key}?->toDateString() ?? '') : (string)$row->{$key};} unset($this->formDoctors); Flux::modal('visit-form')->show(); }
    public function nextIn(int $days): void { $base=$this->form['visited_at'] ?: today()->toDateString(); $this->form['next_visit_at']=\Illuminate\Support\Carbon::parse($base)->addDays($days)->toDateString(); }
    public function save(): void {
        $this->validate(['form.doctor_id'=>['required','integer'],'form.branch_id'=>['required','integer'],'form.responsible_user_id'=>['required','integer'],'form.visited_at'=>['nullable','date','before_or_equal:today'],'form.next_visit_at'=>['nullable','date'],'form.comment'=>['nullable','string','max:3000'],'form.agreement'=>['nullable','string','max:3000'],'form.notes'=>['nullable','string','max:3000']],['form.doctor_id.required'=>'اختر الطبيب.']);
        $branch=AccessScope::branches(auth()->user())->findOrFail($this->form['branch_id']);
        AccessScope::doctors(auth()->user())->whereHas('branches',fn($q)=>$q->where('branches.id',$branch->id))->findOrFail($this->form['doctor_id']);
        User::where('is_active',true)->where(fn($q)=>$q->where('branch_id',$branch->id)->orWhere('role','manager'))->findOrFail($this->form['responsible_user_id']);
        \Illuminate\Support\Facades\DB::transaction(function(){ $row=$this->editingId ? $this->query()->findOrFail($this->editingId):new DoctorVisit; $before=$row->toArray(); $row->fill(array_map(fn($v)=>$v==='' ? null:$v,$this->form))->save(); ActivityLogger::log('visit.saved',$row,['before'=>$before,'after'=>$row->toArray()]); });
        Flux::modal('visit-form')->close(); Flux::toast(variant:'success',text:'تم حفظ الزيارة.'); unset($this->performance,$this->rows,$this->summary,$this->upcoming,$this->visits);
    }
    public function delete(int $id): void { $row=$this->query()->findOrFail($id); \Illuminate\Support\Facades\DB::transaction(function() use($row){ActivityLogger::log('visit.deleted',$row);$row->delete();}); unset($this->performance,$this->rows,$this->summary,$this->upcoming,$this->visits); }
    public array $booklet=['doctor_id'=>null,'quantity'=>'1','note'=>''];
    public function askBooklet(int $doctorId): void { AccessScope::doctors(auth()->user())->findOrFail($doctorId); $this->booklet=['doctor_id'=>$doctorId,'quantity'=>'1','note'=>'']; Flux::modal('booklet-form')->show(); }
    public function requestBooklet(): void {
        $this->validate(['booklet.quantity'=>['required','integer','min:1','max:50'],'booklet.note'=>['nullable','string','max:500']]);
        $doctor=AccessScope::doctors(auth()->user())->findOrFail($this->booklet['doctor_id']);
        $branchId=$this->branch ?: (auth()->user()->branch_id ?? $doctor->branches()->value('branches.id'));
        $r=BookletRequest::create(['doctor_id'=>$doctor->id,'branch_id'=>$branchId,'quantity'=>(int)$this->booklet['quantity'],'note'=>trim((string)$this->booklet['note']) ?: null,'source'=>'staff','requested_by'=>auth()->id()]);
        ActivityLogger::log('booklet.requested',$r,['doctor'=>$doctor->display_name,'quantity'=>$r->quantity]);
        Flux::modal('booklet-form')->close(); unset($this->booklets); Flux::toast(variant:'success',text:'تم تسجيل طلب الدفتر.');
    }
    public function deliverBooklet(int $id): void {
        $r=BookletRequest::when(! auth()->user()->isAdmin(),fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))->findOrFail($id);
        $r->update(['status'=>'delivered','delivered_at'=>now(),'delivered_by'=>auth()->id()]); ActivityLogger::log('booklet.delivered',$r);
        unset($this->booklets); Flux::toast(variant:'success',text:'تم تسجيل تسليم الدفتر.');
    }
}; ?>
<div class="mx-auto w-full max-w-7xl space-y-5">
<x-page-header eyebrow="الدعاية" title="زيارات الأطباء" subtitle="أداء كل طبيب، آخر زيارة والزيارة القادمة، وتنبيه فوري لو طبيب قلّل أو وقف الحالات">
    <x-slot:actions><flux:button variant="primary" icon="plus" wire:click="create">تسجيل زيارة</flux:button></x-slot:actions>
</x-page-header>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
    @foreach([['alerts','تحتاج تدخل','exclamation-triangle','text-red-600'],['overdue','زيارات مستحقة','calendar','text-amber-600'],['visits','زيارات هذا الشهر','check-badge','text-brand-600'],['cases','حالات آخر 30 يوم','folder-open','text-brand-600'],['doctors','الأطباء','academic-cap','text-zinc-500']] as [$k,$label,$icon,$tone])
        <button type="button" wire:click="$set('status', '{{ $k==='alerts' ? 'stopped' : ($k==='overdue' ? 'overdue' : '') }}')" class="rounded-xl border border-zinc-200 bg-white p-4 text-start transition hover:border-brand-300 hover:shadow-sm">
            <div class="flex items-center justify-between text-sm text-zinc-500">{{ $label }}<flux:icon :name="$icon" variant="mini" class="{{ $tone }}" /></div>
            <div class="mt-2 text-2xl font-bold ltr-nums {{ $k==='alerts' && $this->summary[$k] ? 'text-red-600' : 'text-zinc-900' }}">{{ $this->summary[$k] }}</div>
        </button>
    @endforeach
</div>

@if($this->booklets->isNotEmpty())
<x-panel title="طلبات دفاتر التحويل" icon="book-open">
    <div class="divide-y">@foreach($this->booklets as $b)
        <div class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="bk-{{ $b->id }}">
            <div><span class="font-medium">{{ $b->doctor?->display_name }}</span> <span class="text-sm text-zinc-500">· {{ $b->quantity }} دفتر · {{ $b->branch?->name }} · {{ $b->created_at->diffForHumans() }} · {{ $b->source==='doctor' ? 'طلب من الطبيب' : 'سجّله '.$b->requester?->name }}</span>@if($b->note)<p class="text-sm text-zinc-600">{{ $b->note }}</p>@endif</div>
            <flux:button size="sm" variant="primary" icon="check" wire:click="deliverBooklet({{ $b->id }})">تم التسليم</flux:button>
        </div>
    @endforeach</div>
</x-panel>
@endif

<div class="grid gap-5 xl:grid-cols-3">
<x-panel title="أداء الأطباء" icon="chart-bar" :padded="false" class="xl:col-span-2">
    <div class="flex flex-wrap items-end gap-3 border-b border-zinc-100 p-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="ابحث باسم الطبيب أو الكود" class="min-w-48 flex-1" clearable />
        <flux:select wire:model.live="status" class="max-w-52"><flux:select.option value="">كل الحالات</flux:select.option>@foreach(DoctorPerformance::STATUSES as $k=>[$l])<flux:select.option :value="$k">{{ $l }}</flux:select.option>@endforeach<flux:select.option value="overdue">زيارة مستحقة</flux:select.option><flux:select.option value="never">لم يُزر أبدًا</flux:select.option></flux:select>
        @if(auth()->user()->isAdmin() || count(AccessScope::branchIds(auth()->user()))>1)<flux:select wire:model.live="branch" class="max-w-48"><flux:select.option value="">كل الفروع</flux:select.option>@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select>@endif
        @if($doctor)<flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('doctor','')">عرض الكل</flux:button>@endif
    </div>
    <div class="overflow-x-auto">
    <table class="w-full min-w-[760px] text-sm">
        <thead><tr class="border-b text-zinc-500"><th class="p-3 text-start font-medium">الطبيب</th><th class="p-3 text-start font-medium">آخر 30 يوم</th><th class="p-3 text-start font-medium">قبل / بعد آخر زيارة</th><th class="p-3 text-start font-medium">آخر زيارة</th><th class="p-3 text-start font-medium">الزيارة القادمة</th><th></th></tr></thead>
        <tbody>
        @forelse($this->rows as $r)
            @php([$label,$color] = DoctorPerformance::STATUSES[$r['status']])
            <tr class="border-b align-top" wire:key="perf-{{ $r['doctor']->id }}">
                <td class="p-3">
                    <div class="font-medium">{{ $r['doctor']->display_name }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2"><flux:badge size="sm" :color="$color">{{ $label }}</flux:badge>@if($r['last_case'])<span class="text-xs text-zinc-500">آخر حالة {{ $r['last_case']->format('d/m') }}</span>@endif</div>
                </td>
                <td class="p-3">
                    <div class="text-lg font-bold ltr-nums">{{ $r['last30'] }}</div>
                    <div class="text-xs text-zinc-500">الشهر السابق <span class="ltr-nums">{{ $r['prev30'] }}</span>
                        @if($r['change'] !== null)<span class="ms-1 font-semibold ltr-nums {{ $r['change'] < 0 ? 'text-red-600' : ($r['change'] > 0 ? 'text-green-700' : 'text-zinc-500') }}">{{ $r['change'] > 0 ? '▲' : ($r['change'] < 0 ? '▼' : '') }}{{ abs($r['change']) }}%</span>@endif
                    </div>
                </td>
                <td class="p-3">
                    @if($r['last_visit'])
                        <div class="ltr-nums font-medium">{{ $r['before_visit'] }} ← {{ $r['after_visit'] }}</div>
                        <div class="text-xs {{ $r['after_visit'] > $r['before_visit'] ? 'text-green-700' : ($r['after_visit'] < $r['before_visit'] ? 'text-red-600' : 'text-zinc-500') }}">{{ $r['after_visit'] > $r['before_visit'] ? 'الزيارة رفعت الحالات' : ($r['after_visit'] < $r['before_visit'] ? 'الحالات قلت بعد الزيارة' : 'بدون تغيير') }}</div>
                    @else<span class="text-xs text-zinc-400">—</span>@endif
                </td>
                <td class="p-3 ltr-nums">{{ $r['last_visit']?->format('d/m/Y') ?? 'لم يُزر' }}</td>
                <td class="p-3">@if($r['next_visit'])<span class="ltr-nums {{ $r['next_visit']->lt(today()) ? 'font-semibold text-red-600' : ($r['next_visit']->isToday() ? 'font-semibold text-amber-600' : '') }}">{{ $r['next_visit']->format('d/m/Y') }}</span>@else<span class="text-zinc-400">غير محددة</span>@endif</td>
                <td class="p-3 text-end whitespace-nowrap">
                    <flux:button size="sm" variant="primary" wire:click="create({{ $r['doctor']->id }})">زيارة</flux:button>
                    <flux:button size="sm" variant="ghost" icon="book-open" wire:click="askBooklet({{ $r['doctor']->id }})" aria-label="طلب دفتر" tooltip="طلب دفتر" />
                    @if($r['doctor']->location_url)<flux:button size="sm" variant="ghost" icon="map-pin" :href="$r['doctor']->location_url" target="_blank" aria-label="اللوكيشن" />@endif
                    @if($wa = $r['doctor']->whatsappNumber())<a href="{{ Phone::whatsappUrl($wa) }}" target="_blank" rel="noopener" class="inline-flex size-8 items-center justify-center rounded-lg text-brand-600 hover:bg-brand-50" aria-label="واتساب"><x-whatsapp-icon class="size-4" /></a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-zinc-500">لا يوجد أطباء بهذا الفلتر.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <p class="border-t border-zinc-100 p-3 text-xs text-zinc-500">«قبل / بعد» = حالات الطبيب في الـ 30 يومًا قبل آخر زيارة مقابل بعدها. التنبيهات تصل للمدير ومسؤول الدعاية في «التنبيهات».</p>
</x-panel>

<x-panel title="الزيارات القادمة" icon="calendar-days">
    <div class="space-y-2">
    @forelse($this->upcoming as $v)
        @php($days = (int) today()->diffInDays($v->next_visit_at, false))
        <div class="flex items-center gap-3 rounded-lg border p-3 {{ $days < 0 ? 'border-red-200 bg-red-50' : ($days === 0 ? 'border-amber-200 bg-amber-50' : 'border-zinc-200') }}" wire:key="up-{{ $v->id }}">
            <div class="w-12 shrink-0 text-center"><div class="text-lg font-bold ltr-nums">{{ $v->next_visit_at->format('d') }}</div><div class="text-xs text-zinc-500">{{ $v->next_visit_at->translatedFormat('M') }}</div></div>
            <div class="min-w-0 flex-1"><div class="truncate font-medium">{{ $v->doctor?->display_name }}</div><div class="text-xs {{ $days < 0 ? 'text-red-700' : 'text-zinc-500' }}">{{ $days < 0 ? 'متأخرة '.abs($days).' يوم' : ($days === 0 ? 'اليوم' : 'بعد '.$days.' يوم') }} · {{ $v->responsible?->name }}</div></div>
            <flux:button size="sm" wire:click="create({{ $v->doctor_id }})">تمت</flux:button>
        </div>
    @empty<p class="text-sm text-zinc-500">لا توجد زيارات مستحقة خلال أسبوعين.</p>@endforelse
    </div>
</x-panel>
</div>

<x-panel title="سجل الزيارات" icon="clipboard-document-list"><div class="divide-y">@forelse($this->visits as $visit)<div class="py-4" wire:key="v-{{ $visit->id }}"><div class="flex flex-wrap justify-between gap-3"><div><strong>{{ $visit->doctor?->display_name }}</strong><span class="ms-2 text-sm text-zinc-500">{{ $visit->branch?->name }} · {{ $visit->responsible?->name }}</span></div><div><flux:button size="sm" wire:click="edit({{ $visit->id }})">تعديل</flux:button><flux:button size="sm" variant="ghost" wire:click="delete({{ $visit->id }})" wire:confirm="حذف هذه الزيارة؟">حذف</flux:button></div></div><p class="mt-2 text-sm">{{ $visit->visited_at ? 'تمت الزيارة: '.$visit->visited_at->format('d/m/Y') : 'موعد مخطط' }} · القادمة: {{ $visit->next_visit_at?->format('d/m/Y') ?? 'غير محدد' }}</p>@foreach(['comment'=>'تعليق','agreement'=>'الاتفاق','notes'=>'ملاحظات'] as $key=>$label)@if($visit->{$key})<p class="mt-1 whitespace-pre-line text-sm text-zinc-600">{{ $label }}: {{ $visit->{$key} }}</p>@endif @endforeach</div>@empty<p class="text-sm text-zinc-500">لا توجد زيارات مسجلة.</p>@endforelse</div>{{ $this->visits->links() }}</x-panel>

<flux:modal name="booklet-form" class="w-full max-w-sm"><form wire:submit="requestBooklet" class="space-y-4">
    <flux:heading size="lg">طلب دفتر تحويل</flux:heading>
    <flux:input wire:model="booklet.quantity" type="number" min="1" max="50" label="عدد الدفاتر" />
    <flux:input wire:model="booklet.note" label="ملاحظة (اختياري)" />
    <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">إلغاء</flux:button></flux:modal.close><flux:button type="submit" variant="primary">تسجيل الطلب</flux:button></div>
</form></flux:modal>

<flux:modal name="visit-form" class="w-full max-w-xl"><form wire:submit="save" class="space-y-4">
    <flux:heading size="lg">{{ $editingId ? 'تعديل الزيارة' : 'تسجيل زيارة' }}</flux:heading>
    <div class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="form.branch_id" label="الفرع">@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select>
        <flux:select wire:model="form.responsible_user_id" label="مسؤول الزيارة">@foreach(User::where('is_active',true)->where(fn($q)=>$q->where('branch_id',$form['branch_id'])->orWhere('role','manager'))->orderBy('name')->get() as $u)<flux:select.option :value="(string)$u->id">{{ $u->name }}</flux:select.option>@endforeach</flux:select>
    </div>
    <x-search-select model="form.doctor_id" label="الطبيب" placeholder="اكتب أول حرفين من اسم الطبيب" wire:key="visit-doctor-{{ $form['branch_id'] }}" :options="$this->formDoctors->map(fn ($d) => [$d->id, $d->display_name, $d->code])" />
    <flux:error name="form.doctor_id" />
    <div class="grid gap-3 sm:grid-cols-2">
        <flux:input wire:model="form.visited_at" label="تاريخ الزيارة" type="date" description="اتركه فارغًا لو موعد مخطط فقط" />
        <div><flux:input wire:model="form.next_visit_at" label="الزيارة القادمة" type="date" />
            <div class="mt-2 flex gap-1">@foreach([7=>'أسبوع',14=>'أسبوعين',30=>'شهر'] as $d=>$l)<flux:button size="xs" variant="ghost" wire:click="nextIn({{ $d }})">+{{ $l }}</flux:button>@endforeach</div>
        </div>
    </div>
    <flux:textarea wire:model="form.comment" label="تعليق الزيارة" rows="2" />
    <flux:textarea wire:model="form.agreement" label="آخر ما تم الاتفاق عليه" rows="2" />
    <flux:textarea wire:model="form.notes" label="ملاحظات" rows="2" />
    <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">إلغاء</flux:button></flux:modal.close><flux:button type="submit" variant="primary">حفظ الزيارة</flux:button></div>
</form></flux:modal>
</div>
