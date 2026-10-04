<?php
use App\Models\Branch;
use Livewire\Attributes\{Locked,Title};
use Livewire\Component;
new #[Title('بيانات الفرع')] class extends Component {
    #[Locked] public Branch $branch;
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    public function mount(Branch $branch): void { $this->branch=$branch; }
}; ?>
<div class="mx-auto w-full max-w-6xl space-y-5"><x-page-header :title="$branch->name" :subtitle="$branch->address" :back="route('branches.index')" /><div class="grid gap-3 sm:grid-cols-3"><x-stat-card label="الحالات" :value="$branch->cases()->count()" icon="folder-open" :href="route('cases.index',['branch'=>$branch->id])" /><x-stat-card label="المرضى" :value="$branch->patients()->count()" icon="users" /><x-stat-card label="الفريق" :value="$branch->users()->count()" icon="identification" /></div>
<div class="grid gap-5 lg:grid-cols-2"><x-panel title="المستخدمون والفنيون"><div class="divide-y">@foreach($branch->users as $user)<div class="flex justify-between py-3 text-sm"><span>{{ $user->name }}</span><flux:badge :color="$user->role->color()">{{ $user->role->label() }}</flux:badge></div>@endforeach</div><a class="text-sm text-brand-700 underline" href="{{ route('users.index') }}">إدارة المستخدمين</a></x-panel><x-panel title="الأطباء"><div class="divide-y">@foreach($branch->doctors()->with('user')->get() as $doctor)<div class="flex justify-between py-3 text-sm"><span>{{ $doctor->display_name }}</span><strong>{{ $doctor->cases()->where('branch_id',$branch->id)->count() }} حالة</strong></div>@endforeach</div></x-panel></div>
<x-panel title="الفحوصات والأسعار"><div class="divide-y">@foreach(\App\Models\ExamType::active()->get() as $exam)@php($override=$exam->branches()->where('branches.id',$branch->id)->first())<div class="flex justify-between py-3 text-sm"><span>{{ $exam->name }} @if($override && !$override->pivot->is_active)<small>متوقف في الفرع</small>@endif</span><strong>{{ \App\Support\Money::display(\App\Services\CasePricing::quote($exam,$branch)) }} {{ \App\Models\Setting::get('currency') }}</strong></div>@endforeach</div><a href="{{ route('exam-types.index') }}" class="text-sm text-brand-700 underline">تعديل أسعار الفروع</a></x-panel>
<x-panel title="حسابات الفرع"><a href="{{ route('accounts.index',['branch'=>$branch->id]) }}" class="text-brand-700 underline">فتح الإيرادات والحسابات والتقارير</a></x-panel></div>
