<?php
use App\Models\MedicalCase;
use App\Support\AccessScope;
use App\Enums\Permission;
use Livewire\Attributes\{Computed,Title,Url};
use Livewire\Component;
use Livewire\WithPagination;
new #[Title('حالات الفني')] class extends Component {
    use WithPagination;
    #[Url] public string $selected='';
    public string $search='';
    public function boot(): void { abort_unless(auth()->user()->isTechnician() && auth()->user()->hasPermission(Permission::ViewCases),403); }
    #[Computed] public function cases() { return AccessScope::cases(auth()->user())->with(['patient','examType'])->search($this->search)->orderByRaw("CASE workflow_status WHEN 'new' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")->latest()->paginate(20); }
    #[Computed] public function selectedCase(): ?MedicalCase { return $this->selected ? AccessScope::cases(auth()->user())->findOrFail($this->selected):null; }
    public function updatingSearch(): void { $this->resetPage(); }
}; ?>
<div class="mx-auto w-full max-w-6xl space-y-5"><x-page-header title="الحالات المسندة إليّ" subtitle="ابدأ الفحص، ارفع النتائج، ثم حدد اكتمال الحالة" /><flux:input wire:model.live.debounce.300ms="search" label="البحث في الحالات" placeholder="اسم المريض أو كود الحالة" />
@if($this->selectedCase)<flux:button wire:click="$set('selected','')" icon="arrow-right">العودة إلى الحالات</flux:button><livewire:case-clinical-panel :medical-case="$this->selectedCase" :key="'tech-case-'.$selected" />@else<x-panel :padded="false"><div class="divide-y">@forelse($this->cases as $case)<button type="button" wire:click="$set('selected','{{ $case->id }}')" class="flex min-h-16 w-full flex-wrap items-center justify-between gap-3 p-4 text-start hover:bg-brand-50"><div><div class="font-semibold">{{ $case->patient->name }}</div><div class="text-sm text-zinc-600">{{ $case->case_code }} · {{ $case->examType?->name ?? 'لم يحدد الفحص' }}</div></div><flux:badge :color="$case->workflow_status->color()">{{ $case->workflow_status->label() }}</flux:badge></button>@empty<p class="p-6 text-zinc-600">لا توجد حالات مسندة إليك في فرعك.</p>@endforelse</div>{{ $this->cases->links() }}</x-panel>@endif</div>
