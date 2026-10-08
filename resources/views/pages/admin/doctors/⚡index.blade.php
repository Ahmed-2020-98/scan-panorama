<?php

use App\Models\Doctor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('الأطباء')] class extends Component {
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Computed]
    public function doctors()
    {
        $term = trim($this->search);

        return Doctor::query()
            ->with(['user', 'branches'])
            ->withCount('cases')
            ->withMax('cases', 'exam_date')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
            ->orderByName()
            ->get();
    }

    public function delete(int $id): void
    {
        \App\Services\RecordRecovery::delete(auth()->user(), Doctor::findOrFail($id));
        unset($this->doctors);
        \Flux\Flux::toast(variant: 'success', text: 'تم حذف الطبيب. يمكن استرجاعه من «المحذوفات» خلال '.(int) config('radiology.doctor_restore_days', 30).' يومًا.');
    }
}; ?>

<div class="mx-auto w-full max-w-7xl">
    <x-page-header eyebrow="الإدارة" title="الأطباء" subtitle="حسابات الأطباء وأكوادهم والفروع المرتبطين بها">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('doctors.create')" wire:navigate>إضافة طبيب</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :padded="false">
        <div class="border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="ابحث بالاسم أو الكود أو الهاتف" class="max-w-md" clearable />
        </div>

        @if ($this->doctors->isEmpty())
            <x-empty-state icon="academic-cap" title="لا يوجد أطباء" text="أضف الطبيب لإنشاء حسابه وربطه بالحالات." />
        @else
            <div class="px-4 pb-2">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>الطبيب</flux:table.column>
                        <flux:table.column>الكود</flux:table.column>
                        <flux:table.column>الهاتف</flux:table.column>
                        <flux:table.column>الفروع</flux:table.column>
                        <flux:table.column>الحالات</flux:table.column>
                        <flux:table.column>آخر حالة</flux:table.column>
                        <flux:table.column>آخر دخول</flux:table.column>
                        <flux:table.column>الحساب</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->doctors as $doctor)
                            <flux:table.row :key="$doctor->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="sm" :name="$doctor->user->name" color="teal" />
                                        <div>
                                            <div class="font-medium text-zinc-900">{{ $doctor->display_name }}</div>
                                            <div class="text-xs text-zinc-500">{{ $doctor->specialty ?? '—' }}</div>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="ltr-nums" variant="strong">{{ $doctor->code }}</flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $doctor->user->phone ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($doctor->branches as $branch)
                                            <flux:badge size="sm" color="sky">{{ $branch->name }}</flux:badge>
                                        @empty
                                            —
                                        @endforelse
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <a href="{{ route('cases.index', ['doctor' => $doctor->id]) }}" wire:navigate class="ltr-nums text-brand-700 hover:underline">{{ $doctor->cases_count }}</a>
                                </flux:table.cell>
                                <flux:table.cell class="ltr-nums">{{ $doctor->cases_max_exam_date ? \Illuminate\Support\Carbon::parse($doctor->cases_max_exam_date)->format('d/m/Y') : '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $doctor->user->last_login_at?->diffForHumans() ?? 'لم يدخل بعد' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$doctor->user->is_active ? 'green' : 'red'">{{ $doctor->user->is_active ? 'مفعّل' : 'معطّل' }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('doctors.edit', $doctor)" wire:navigate>تعديل</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600!" wire:click="delete({{ $doctor->id }})"
                                        wire:confirm="{{ 'حذف '.$doctor->display_name.'؟ سيتعطل حسابه ويختفي من قوائم الأطباء'.($doctor->cases_count ? '، وتبقى حالاته الـ'.$doctor->cases_count.' محفوظة باسمه' : '').'. يمكن استرجاعه بحالته كاملة من «المحذوفات» خلال '.(int) config('radiology.doctor_restore_days', 30).' يومًا.' }}">حذف</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-panel>
</div>
