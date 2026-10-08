<?php

use App\Models\Branch;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الفروع')] class extends Component {
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }

    #[\Livewire\Attributes\Locked] public ?int $editingId = null;

    public string $name = '';

    public string $address = '';

    public string $phone = '';

    public bool $is_active = true;

    #[Computed]
    public function branches()
    {
        return Branch::withCount(['cases' => fn ($q) => $q->withTrashed(), 'cases as live_cases_count', 'doctors', 'users'])->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('branch-form')->show();
    }

    public function edit(int $id): void
    {
        $branch = Branch::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $branch->id;
        $this->name = $branch->name;
        $this->is_active = (bool) $branch->is_active;
        $this->address = (string) $branch->address;
        $this->phone = (string) $branch->phone;
        Flux::modal('branch-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);
        $validated['address'] = trim($validated['address'] ?? '') ?: null;
        $validated['phone'] = trim($validated['phone'] ?? '') ?: null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $branch = $this->editingId ? Branch::findOrFail($this->editingId) : new Branch;
            $before = $branch->only(array_keys($validated));
            $creating = ! $branch->exists;
            $branch->fill($validated)->save();
            \App\Support\ActivityLogger::log($creating ? 'branch.created' : 'branch.updated', $branch, ['before'=>$before,'after'=>$validated]);
        });

        Flux::modal('branch-form')->close();
        Flux::toast(variant: 'success', text: 'تم حفظ الفرع.');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $branch = Branch::findOrFail($id);

        if ($branch->users()->exists()) {
            Flux::toast(variant: 'danger', text: 'انقل مستخدمي هذا الفرع إلى فرع آخر من «المستخدمون والصلاحيات» قبل حذفه.');

            return;
        }

        \App\Services\RecordRecovery::delete(auth()->user(), $branch);
        unset($this->branches);
        Flux::toast(variant: 'success', text: 'تم حذف الفرع وحالاته إلى «المحذوفات»، ويمكن استرجاعها من هناك.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'address', 'phone', 'is_active');
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-5xl">
    <x-page-header eyebrow="الإدارة" title="الفروع" subtitle="فروع المركز التي تُسجَّل فيها الحالات">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">إضافة فرع</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :padded="false">
        <div class="px-4 pb-2">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>الفرع</flux:table.column>
                    <flux:table.column>العنوان</flux:table.column>
                    <flux:table.column>الهاتف</flux:table.column>
                    <flux:table.column>الأطباء</flux:table.column>
                    <flux:table.column>الحالات</flux:table.column>
                    <flux:table.column>الحالة</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->branches as $branch)
                        <flux:table.row :key="$branch->id">
                            <flux:table.cell variant="strong"><a class="text-brand-700 hover:underline" href="{{ route('branches.show',$branch) }}" wire:navigate>{{ $branch->name }}</a></flux:table.cell>
                            <flux:table.cell>{{ $branch->address ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="ltr-nums">{{ $branch->phone ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="ltr-nums">{{ $branch->doctors_count }}</flux:table.cell>
                            <flux:table.cell class="ltr-nums">{{ $branch->cases_count }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$branch->is_active ? 'green' : 'zinc'">{{ $branch->is_active ? 'نشط' : 'متوقف' }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $branch->id }})">تعديل</flux:button>
                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600!" wire:click="delete({{ $branch->id }})"
                                    wire:confirm="{{ $branch->live_cases_count > 0 ? 'حذف فرع '.$branch->name.' و'.$branch->live_cases_count.' حالة بملفاتها؟ ستنتقل كلها إلى «المحذوفات» ويمكن استرجاعها. المدفوعات تبقى في الحسابات.' : 'حذف فرع '.$branch->name.'؟ يمكن استرجاعه من «المحذوفات».' }}">حذف</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </x-panel>

    <flux:modal name="branch-form" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'تعديل الفرع' : 'إضافة فرع' }}</flux:heading>
            <flux:input wire:model="name" label="اسم الفرع" required />
            <flux:input wire:model="address" label="العنوان" />
            <flux:input wire:model="phone" label="الهاتف" dir="ltr" />
            <flux:switch wire:model="is_active" label="الفرع نشط" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">إلغاء</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">حفظ</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
