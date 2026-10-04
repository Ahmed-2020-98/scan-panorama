<?php
use App\Models\{DoctorVisit,User};
use App\Support\{AccessScope,ActivityLogger};
use App\Enums\Permission;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Computed,Locked,Title};
use Livewire\Component;
use Livewire\WithPagination;
new #[Title('زيارات الأطباء')] class extends Component {
    use WithPagination;
    #[Locked] public ?int $editingId=null;
    public string $branch=''; public string $month=''; public string $filter='';
    public array $form=['doctor_id'=>'','branch_id'=>'','responsible_user_id'=>'','visited_at'=>'','next_visit_at'=>'','comment'=>'','agreement'=>'','notes'=>''];
    public function boot(): void { abort_unless(auth()->user()->hasPermission(Permission::ManageVisits),403); }
    public function mount(): void { $this->month=today()->format('Y-m'); }
    public function updating(): void { $this->resetPage(); }
    private function query() { return DoctorVisit::query()->when(!auth()->user()->isAdmin(),fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))->when($this->branch,fn($q)=>$q->where('branch_id',$this->branch)); }
    #[Computed] public function summary(): array {
        $this->validate(['month'=>['required','date_format:Y-m']]);
        $start=\Illuminate\Support\Carbon::parse($this->month.'-01'); $end=$start->copy()->endOfMonth();
        $doctors=AccessScope::doctors(auth()->user())->when($this->branch,fn($q)=>$q->whereHas('branches',fn($b)=>$b->where('branches.id',$this->branch)));
        $ids=(clone $this->query())->whereBetween('visited_at',[$start->toDateString(),$end->toDateString()])->distinct()->pluck('doctor_id');
        $stale=(int)\App\Models\Setting::get('stale_visit_days',90);
        $recent=(clone $this->query())->where('visited_at','>=',today()->subDays($stale)->toDateString())->pluck('doctor_id');
        return ['visited'=>$ids->count(),'not_visited'=>(clone $doctors)->whereNotIn('id',$ids)->count(),'stale'=>(clone $doctors)->whereNotIn('id',$recent)->count(),'visits'=>(clone $this->query())->whereBetween('visited_at',[$start->toDateString(),$end->toDateString()])->count(),'overdue'=>(clone $this->query())->pendingFollowup()->where('next_visit_at','<',today()->toDateString())->count()];
    }
    #[Computed] public function doctors() {
        $q=AccessScope::doctors(auth()->user())->with('user')->when($this->branch,fn($q)=>$q->whereHas('branches',fn($b)=>$b->where('branches.id',$this->branch)))->orderByName();
        $visits=$this->query()->whereNotNull('visited_at')->get();
        if($this->filter==='not_visited'){$q->whereNotIn('id',$visits->filter(fn($v)=>$v->visited_at->format('Y-m')===$this->month)->pluck('doctor_id'));}
        if($this->filter==='stale'){$q->whereNotIn('id',$visits->filter(fn($v)=>$v->visited_at->gte(today()->subDays((int)\App\Models\Setting::get('stale_visit_days',90))))->pluck('doctor_id'));}
        return $q->get()->map(function($doctor) use($visits){ $rows=$visits->where('doctor_id',$doctor->id); $doctor->visit_count=$rows->count(); $doctor->last_visit=$rows->sortByDesc('visited_at')->first()?->visited_at; $doctor->next_visit=$this->query()->where('doctor_id',$doctor->id)->pendingFollowup()->orderBy('next_visit_at')->first()?->next_visit_at; return $doctor; });
    }
    #[Computed] public function visits() { return $this->query()->with(['doctor.user','branch','responsible'])->latest('id')->paginate(20); }
    public function create(): void { $this->reset('editingId','form'); $this->resetValidation(); $this->form['branch_id']=(string)(auth()->user()->branch_id ?? AccessScope::branches(auth()->user())->value('id')); $this->form['responsible_user_id']=(string)auth()->id(); Flux::modal('visit-form')->show(); }
    public function edit(int $id): void { $row=$this->query()->findOrFail($id); $this->editingId=$id; foreach(array_keys($this->form) as $key){$this->form[$key]=in_array($key,['visited_at','next_visit_at'],true) ? ($row->{$key}?->toDateString() ?? '') : (string)$row->{$key};} Flux::modal('visit-form')->show(); }
    public function save(): void {
        $this->validate(['form.doctor_id'=>['required','integer'],'form.branch_id'=>['required','integer'],'form.responsible_user_id'=>['required','integer'],'form.visited_at'=>['nullable','date','before_or_equal:today'],'form.next_visit_at'=>['nullable','date'],'form.comment'=>['nullable','string','max:3000'],'form.agreement'=>['nullable','string','max:3000'],'form.notes'=>['nullable','string','max:3000']]);
        $branch=AccessScope::branches(auth()->user())->findOrFail($this->form['branch_id']);
        $doctor=AccessScope::doctors(auth()->user())->whereHas('branches',fn($q)=>$q->where('branches.id',$branch->id))->findOrFail($this->form['doctor_id']);
        $responsible=User::where('is_active',true)->where(fn($q)=>$q->where('branch_id',$branch->id)->orWhere('role','manager'))->findOrFail($this->form['responsible_user_id']);
        \Illuminate\Support\Facades\DB::transaction(function(){ $row=$this->editingId ? $this->query()->findOrFail($this->editingId):new DoctorVisit; $before=$row->toArray(); $row->fill(array_map(fn($v)=>$v==='' ? null:$v,$this->form))->save(); ActivityLogger::log('visit.saved',$row,['before'=>$before,'after'=>$row->toArray()]); });
        Flux::modal('visit-form')->close(); Flux::toast(text:'تم حفظ الزيارة.');
    }
    public function delete(int $id): void { $row=$this->query()->findOrFail($id); \Illuminate\Support\Facades\DB::transaction(function() use($row){ActivityLogger::log('visit.deleted',$row);$row->delete();}); }
}; ?>
<div class="mx-auto w-full max-w-7xl space-y-5"><x-page-header title="زيارات الأطباء" subtitle="متابعة الزيارات والاتفاقات والمواعيد القادمة"><x-slot:actions><flux:button variant="primary" icon="plus" wire:click="create">تسجيل زيارة / موعد</flux:button></x-slot:actions></x-page-header>
<x-panel><div class="grid gap-3 sm:grid-cols-3"><flux:input wire:model.live="month" label="الشهر" type="month" /><flux:select wire:model.live="branch" label="الفرع"><flux:select.option value="">الفروع المسموح بها</flux:select.option>@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select><flux:select wire:model.live="filter" label="الأطباء"><flux:select.option value="">جميع الأطباء</flux:select.option><flux:select.option value="not_visited">لم تتم زيارتهم هذا الشهر</flux:select.option><flux:select.option value="stale">لم تتم زيارتهم منذ فترة</flux:select.option></flux:select></div></x-panel>
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">@foreach(['visited'=>'أطباء تمت زيارتهم','not_visited'=>'لم يزاروا هذا الشهر','stale'=>'متأخرون عن المتابعة','visits'=>'زيارات هذا الشهر','overdue'=>'مواعيد متأخرة'] as $key=>$label)<x-stat-card :label="$label" :value="$this->summary[$key]" icon="calendar-days" />@endforeach</div>
<x-panel title="متابعة الأطباء"><div class="grid gap-3 sm:grid-cols-2">@foreach($this->doctors as $doctor)<div class="rounded-lg border p-3"><div class="font-medium">{{ $doctor->display_name }}</div><dl class="mt-2 text-sm text-zinc-600"><div>عدد الزيارات: {{ $doctor->visit_count }}</div><div>آخر زيارة: {{ $doctor->last_visit?->format('d/m/Y') ?? 'لم يزر بعد' }}</div><div>الموعد القادم: {{ $doctor->next_visit?->format('d/m/Y') ?? 'غير محدد' }}</div></dl></div>@endforeach</div></x-panel>
<x-panel title="سجل الزيارات"><div class="divide-y">@foreach($this->visits as $visit)<div class="py-4"><div class="flex flex-wrap justify-between gap-3"><div><strong>{{ $visit->doctor->display_name }}</strong><span class="ms-2 text-sm text-zinc-500">{{ $visit->branch->name }} · {{ $visit->responsible->name }}</span></div><div><flux:button size="sm" wire:click="edit({{ $visit->id }})">تعديل</flux:button><flux:button size="sm" variant="ghost" wire:click="delete({{ $visit->id }})" wire:confirm="حذف هذه الزيارة؟">حذف</flux:button></div></div><p class="mt-2 text-sm">{{ $visit->visited_at ? 'تمت الزيارة: '.$visit->visited_at->format('d/m/Y') : 'موعد مخطط' }} · القادمة: {{ $visit->next_visit_at?->format('d/m/Y') ?? 'غير محدد' }}</p>@foreach(['comment'=>'تعليق','agreement'=>'الاتفاق','notes'=>'ملاحظات'] as $key=>$label)@if($visit->{$key})<p class="mt-1 whitespace-pre-line text-sm text-zinc-600">{{ $label }}: {{ $visit->{$key} }}</p>@endif @endforeach</div>@endforeach</div>{{ $this->visits->links() }}</x-panel>
<flux:modal name="visit-form" class="w-full max-w-xl"><form wire:submit="save" class="space-y-4"><flux:heading size="lg">بيانات الزيارة</flux:heading><flux:select wire:model.live="form.branch_id" label="الفرع">@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="form.doctor_id" label="الطبيب"><flux:select.option value="">اختر الطبيب</flux:select.option>@foreach(AccessScope::doctors(auth()->user())->whereHas('branches',fn($q)=>$q->where('branches.id',$form['branch_id']))->with('user')->get() as $doctor)<flux:select.option :value="(string)$doctor->id">{{ $doctor->display_name }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="form.responsible_user_id" label="مسؤول الزيارة">@foreach(User::where('is_active',true)->where(fn($q)=>$q->where('branch_id',$form['branch_id'])->orWhere('role','manager'))->get() as $u)<flux:select.option :value="(string)$u->id">{{ $u->name }}</flux:select.option>@endforeach</flux:select><div class="grid gap-3 sm:grid-cols-2"><flux:input wire:model="form.visited_at" label="تاريخ الزيارة الفعلية (اختياري)" type="date" /><flux:input wire:model="form.next_visit_at" label="موعد الزيارة القادمة" type="date" /></div><flux:textarea wire:model="form.comment" label="تعليق الزيارة" rows="2" /><flux:textarea wire:model="form.agreement" label="آخر ما تم الاتفاق عليه" rows="2" /><flux:textarea wire:model="form.notes" label="ملاحظات" rows="2" /><flux:button type="submit" variant="primary">حفظ الزيارة</flux:button></form></flux:modal></div>
