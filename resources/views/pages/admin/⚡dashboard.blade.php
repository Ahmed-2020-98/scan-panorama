<?php
use App\Models\{MedicalCase,Patient,Doctor,User,DoctorVisit,Setting};
use App\Support\{AccessScope,Money};
use App\Enums\Permission;
use App\Services\FinancialReport;
use Livewire\Attributes\{Computed,Title};
use Livewire\Component;
new #[Title('لوحة التحكم')] class extends Component {
    #[\Livewire\Attributes\Url] public string $branch='';
    public function boot(): void { abort_unless(auth()->user()->isStaff() && auth()->user()->hasPermission(Permission::ViewCases),403); }
    private function query() { return AccessScope::cases(auth()->user())->when($this->branch,fn($q)=>$q->where('branch_id',$this->branch)); }
    #[Computed] public function stats(): array {
        $q=$this->query();
        return ['today'=>(clone $q)->whereDate('exam_date',today())->count(),'total'=>(clone $q)->count(),'month'=>(clone $q)->whereDate('exam_date','>=',today()->startOfMonth())->whereDate('exam_date','<=',today()->endOfMonth())->count(),'new'=>(clone $q)->where('workflow_status','new')->count(),'in_progress'=>(clone $q)->where('workflow_status','in_progress')->count(),'completed'=>(clone $q)->where('workflow_status','completed')->count(),'incomplete'=>(clone $q)->whereDoesntHave('files',fn($q)=>$q->where('storage_status','ready'))->count(),'unopened'=>(clone $q)->whereHas('files',fn($q)=>$q->where('storage_status','ready'))->whereNull('first_opened_at')->count(),'patients'=>AccessScope::patients(auth()->user())->when($this->branch,fn($q)=>$q->whereHas('branches',fn($b)=>$b->where('branches.id',$this->branch)))->count(),'doctors'=>AccessScope::doctors(auth()->user())->active()->when($this->branch,fn($q)=>$q->whereHas('branches',fn($b)=>$b->where('branches.id',$this->branch)))->count(),'users'=>User::when(!auth()->user()->isAdmin(),fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))->when($this->branch,fn($q)=>$q->where('branch_id',$this->branch))->count()];
    }
    #[Computed] public function recentCases() { return $this->query()->with(['patient','doctor.user','examType','branch','technician'])->withFileCounts()->orderByRaw("CASE workflow_status WHEN 'new' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")->latest()->limit(10)->get(); }
    #[Computed] public function byExamType() { return $this->query()->with('examType')->selectRaw('exam_type_id, count(*) as total')->groupBy('exam_type_id')->orderByDesc('total')->get(); }
    #[Computed] public function byBranch() { return $this->query()->with('branch')->selectRaw('branch_id, count(*) as total')->groupBy('branch_id')->orderByDesc('total')->get(); }
    #[Computed] public function doctorStats() { return $this->query()->with('doctor.user')->selectRaw('doctor_id, count(*) as total')->groupBy('doctor_id')->orderByDesc('total')->limit(8)->get(); }
    #[Computed] public function performance() { \App\Services\CenterAlerts::runDaily(); return \App\Services\DoctorPerformance::rows(auth()->user(), $this->branch ? (int)$this->branch : null); }
    #[Computed] public function finance(): array { return auth()->user()->hasPermission(Permission::ViewFinancials) ? FinancialReport::summarize(auth()->user(),['from'=>today()->toDateString(),'to'=>today()->toDateString(),'branch'=>$this->branch]):[]; }
    #[Computed] public function visits(): array {
        if(!auth()->user()->hasPermission(Permission::ManageVisits)){return [];}
        $q=DoctorVisit::when(!auth()->user()->isAdmin(),fn($q)=>$q->whereIn('branch_id',AccessScope::branchIds(auth()->user())))->when($this->branch,fn($q)=>$q->where('branch_id',$this->branch));
        return ['month'=>(clone $q)->whereDate('visited_at','>=',today()->startOfMonth())->whereDate('visited_at','<=',today()->endOfMonth())->count(),'overdue'=>(clone $q)->pendingFollowup()->where('next_visit_at','<',today()->toDateString())->count()];
    }
}; ?>
<div class="mx-auto w-full max-w-7xl space-y-5">
    <x-page-header title="لوحة التشغيل" :subtitle="now()->translatedFormat('l، j F Y').' — '.auth()->user()->name"><x-slot:actions>@if(auth()->user()->hasPermission(Permission::CreateCase))<flux:button variant="primary" icon="plus" :href="route('cases.create')" wire:navigate>حالة جديدة</flux:button>@endif</x-slot:actions></x-page-header>
    @if(auth()->user()->isAdmin())<flux:select wire:model.live="branch" label="الفرع" class="max-w-xs"><flux:select.option value="">جميع الفروع</flux:select.option>@foreach(AccessScope::branches(auth()->user())->get() as $b)<flux:select.option :value="(string)$b->id">{{ $b->name }}</flux:select.option>@endforeach</flux:select>@elseif(!auth()->user()->branch_id)<flux:callout color="amber">يحتاج حسابك إلى تحديد فرع من صفحة المستخدمين قبل بدء العمل.</flux:callout>@endif
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach(['today'=>'حالات اليوم','new'=>'الحالات الجديدة','in_progress'=>'قيد التنفيذ','completed'=>'الحالات المكتملة'] as $key=>$label)<x-stat-card :label="$label" :value="$this->stats[$key]" icon="folder-open" :href="route('cases.index',['branch'=>$branch,'workflow'=>$key==='today' ? '' : $key,'from'=>$key==='today' ? today()->toDateString():'','to'=>$key==='today' ? today()->toDateString():''])" />@endforeach</div>
    <x-panel title="متابعة الحالات" subtitle="الجديدة وقيد التنفيذ أولًا" :padded="false"><x-slot:actions><flux:button size="sm" variant="ghost" :href="route('cases.index')" wire:navigate>كل الحالات</flux:button></x-slot:actions><div class="divide-y">@forelse($this->recentCases as $case)<a href="{{ route('cases.show',$case) }}" wire:navigate class="flex min-h-20 flex-wrap items-center gap-4 px-5 py-4 hover:bg-brand-50/50"><div class="min-w-0 flex-1"><div class="font-semibold">{{ $case->patient->name }}</div><div class="mt-1 text-sm text-zinc-600">{{ $case->case_code }} · {{ $case->examType?->name ?? 'لم يحدد الفحص' }} · {{ $case->branch->name }}</div></div><div class="text-sm text-zinc-600">{{ $case->technician?->name ?? 'الفني لم يحدد' }}</div><flux:badge :color="$case->workflow_status->color()">{{ $case->workflow_status->label() }}</flux:badge><span class="text-sm text-zinc-600">{{ $case->fileCount(\App\Enums\CaseFileType::Report)+$case->fileCount(\App\Enums\CaseFileType::Image) }} صورة / تقرير</span></a>@empty<x-empty-state icon="folder-open" title="لا توجد حالات" text="ابدأ بتسجيل حالة جديدة." />@endforelse</div></x-panel>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach(['total'=>'إجمالي الحالات','patients'=>'عدد المرضى','users'=>'عدد المستخدمين','doctors'=>'الأطباء النشطون'] as $key=>$label)<x-stat-card :label="$label" :value="$this->stats[$key]" icon="chart-bar" />@endforeach</div>
    <div class="grid gap-5 lg:grid-cols-2"><x-panel title="الحالات حسب الفرع"><div class="divide-y">@foreach($this->byBranch as $row)<div class="flex justify-between py-3 text-sm"><span>{{ $row->branch->name }}</span><strong>{{ $row->total }}</strong></div>@endforeach</div></x-panel><x-panel title="الحالات حسب نوع الفحص"><div class="divide-y">@foreach($this->byExamType as $row)<div class="flex justify-between py-3 text-sm"><span>{{ $row->examType?->name ?? 'الفحص لم يحدد' }}</span><strong>{{ $row->total }}</strong></div>@endforeach</div></x-panel></div>
    @php($perf = $this->performance)
    @if($perf->isNotEmpty())
    <div class="grid gap-5 lg:grid-cols-3">
        <x-panel title="معدل الأطباء: آخر 30 يوم مقابل الشهر السابق" icon="chart-bar" class="lg:col-span-2" :padded="false">
            <x-slot:actions>@if(auth()->user()->hasPermission(Permission::ManageVisits))<flux:button size="sm" variant="ghost" :href="route('visits.index')" wire:navigate>كل الأطباء</flux:button>@endif</x-slot:actions>
            @php($top = $perf->sortByDesc(fn ($r) => max($r['last30'], $r['prev30']))->take(8))
            @php($max = max(1, $top->max(fn ($r) => max($r['last30'], $r['prev30']))))
            <div class="divide-y">
                @foreach($top as $r)
                    <div class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-3 px-5 py-2.5 text-sm">
                        <span class="truncate font-medium">{{ $r['doctor']->display_name }}</span>
                        <div class="space-y-1">
                            <div class="h-2 rounded-full bg-brand-600" style="width: {{ max(2, round($r['last30'] * 100 / $max)) }}%" title="آخر 30 يوم: {{ $r['last30'] }}"></div>
                            <div class="h-2 rounded-full bg-zinc-300" style="width: {{ max(2, round($r['prev30'] * 100 / $max)) }}%" title="الشهر السابق: {{ $r['prev30'] }}"></div>
                        </div>
                        <span class="ltr-nums text-end"><b>{{ $r['last30'] }}</b> <span class="text-zinc-400">/ {{ $r['prev30'] }}</span>@if($r['change'] !== null)<span class="ms-1 text-xs font-semibold {{ $r['change'] < 0 ? 'text-red-600' : ($r['change'] > 0 ? 'text-green-700' : 'text-zinc-500') }}">{{ $r['change'] > 0 ? '▲' : ($r['change'] < 0 ? '▼' : '') }}{{ abs($r['change']) }}%</span>@endif</span>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-4 border-t border-zinc-100 px-5 py-2 text-xs text-zinc-500"><span class="inline-flex items-center gap-1.5"><span class="h-2 w-4 rounded-full bg-brand-600"></span>آخر 30 يوم</span><span class="inline-flex items-center gap-1.5"><span class="h-2 w-4 rounded-full bg-zinc-300"></span>الـ 30 يوم السابقة</span></div>
        </x-panel>
        <x-panel title="أطباء يحتاجون متابعة" icon="exclamation-triangle">
            @php($alerts = $perf->whereIn('status', ['stopped', 'declining']))
            <div class="space-y-2">
            @forelse($alerts->take(8) as $r)
                <div class="rounded-lg border p-3 {{ $r['status'] === 'stopped' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}">
                    <div class="font-medium">{{ $r['doctor']->display_name }}</div>
                    <div class="text-xs {{ $r['status'] === 'stopped' ? 'text-red-700' : 'text-amber-800' }}">{{ $r['status'] === 'stopped' ? 'لم يرسل حالات منذ '.$r['quiet_days'].' يوم' : 'نزل من '.$r['prev30'].' إلى '.$r['last30'].' حالة' }}</div>
                </div>
            @empty
                <p class="text-sm text-zinc-500">كل الأطباء منتظمون 👍</p>
            @endforelse
            </div>
        </x-panel>
    </div>
    @endif
    @if($this->visits)<x-panel title="زيارات الأطباء"><div class="flex flex-wrap gap-5 text-sm"><span>{{ $this->visits['month'] }} زيارة هذا الشهر</span><span>{{ $this->visits['overdue'] }} موعد متأخر</span><a class="text-brand-700 underline" href="{{ route('visits.index') }}" wire:navigate>متابعة الزيارات</a></div></x-panel>@endif
    @if($this->finance)<x-panel title="ملخص حسابات اليوم"><div class="grid gap-4 sm:grid-cols-3">@foreach($this->finance['currencies'] as $currency=>$row)<div><div class="text-sm text-zinc-500">المقبوض — {{ $currency }}</div><div class="mt-2 text-xl font-semibold">{{ Money::display((int)$row['collected']) }}</div><div class="mt-1 text-sm text-zinc-600">قيمة الفحوصات {{ Money::display((int)$row['billed']) }} · المتبقي {{ Money::display((int)$row['outstanding']) }}</div></div>@endforeach</div><a class="mt-4 inline-block text-sm text-brand-700 underline" href="{{ route('accounts.index') }}" wire:navigate>فتح الحسابات</a></x-panel>@endif
</div>
