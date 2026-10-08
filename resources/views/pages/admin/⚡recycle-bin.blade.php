<?php
use App\Models\{Branch,Patient,MedicalCase,CaseFile};
use App\Services\RecordRecovery;
use Flux\Flux;
use Livewire\Attributes\{Computed,Title};
use Livewire\Component;
new #[Title('المحذوفات')] class extends Component {
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    #[Computed] public function records(): array { return ['branch'=>Branch::onlyTrashed()->latest('deleted_at')->limit(100)->get(),'patient'=>Patient::onlyTrashed()->latest('deleted_at')->limit(100)->get(),'case'=>MedicalCase::withoutGlobalScope('access')->onlyTrashed()->with('patient')->latest('deleted_at')->limit(100)->get(),'file'=>CaseFile::onlyTrashed()->latest('deleted_at')->limit(100)->get()]; }
    public function restore(string $kind,int $id): void { abort_unless(auth()->user()->isAdmin(),403); $model=match($kind){'branch'=>Branch::class,'patient'=>Patient::class,'case'=>MedicalCase::class,'file'=>CaseFile::class,default=>abort(404)}; RecordRecovery::restore(auth()->user(),$model::onlyTrashed()->findOrFail($id)); Flux::toast(variant:'success',text:'تم الاسترجاع. روابط الحالات تبقى ملغاة حتى إنشاء رابط جديد.'); }
}; ?>
<div class="mx-auto w-full max-w-5xl space-y-5"><x-page-header title="المحذوفات" subtitle="استرجاع الفروع والحالات والمرضى والملفات؛ لا يتم إتلاف الملفات عند الحذف" />
@foreach(['branch'=>'الفروع','patient'=>'المرضى','case'=>'الحالات','file'=>'الملفات'] as $kind=>$title)<x-panel :title="$title"><div class="divide-y">@forelse($this->records[$kind] as $record)<div class="flex items-center justify-between gap-4 py-3"><div><div class="font-medium">{{ $kind === 'file' ? $record->original_name : ($kind === 'case' ? $record->case_code.' — '.$record->patient?->name : ($kind === 'branch' ? $record->name.' — يسترجع حالاته المحذوفة معه' : $record->name)) }}</div><div class="text-xs text-zinc-500">{{ $record->deleted_at->format('d/m/Y H:i') }}</div></div><flux:button icon="arrow-path" wire:click="restore('{{ $kind }}',{{ $record->id }})" wire:confirm="استرجاع هذا العنصر؟">استرجاع</flux:button></div>@empty<p class="py-4 text-sm text-zinc-500">لا توجد عناصر محذوفة.</p>@endforelse</div></x-panel>@endforeach
</div>
