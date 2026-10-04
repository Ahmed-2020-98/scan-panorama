<?php

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('سجل النشاط')] class extends Component {
    public function boot(): void { abort_unless(auth()->user()->isAdmin(),403); }
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $date = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        return ActivityLog::query()
            ->with(['user', 'medicalCase'])
            ->when($this->action, fn ($q) => $q->where('action', $this->action))
            ->when($this->user === 'guest', fn ($q) => $q->whereNull('user_id'))
            ->when($this->user && $this->user !== 'guest', fn ($q) => $q->where('user_id', $this->user))
            ->when($this->date, fn ($q) => $q->whereDate('created_at', $this->date))
            ->latest('id')
            ->paginate(30);
    }
}; ?>

<div class="mx-auto w-full max-w-6xl">
    <x-page-header eyebrow="الإدارة" title="سجل النشاط" subtitle="من أنشأ وعدّل الحالات، ومن فتح وحمّل الملفات" />

    <x-panel :padded="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-3">
            <flux:select wire:model.live="action">
                <flux:select.option value="">كل العمليات</flux:select.option>
                @foreach (ActivityLog::ACTIONS as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="user">
                <flux:select.option value="">كل المستخدمين</flux:select.option>
                <flux:select.option value="guest">زوار رابط المشاركة</flux:select.option>
                @foreach (User::orderBy('name')->get(['id', 'name']) as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="date" />
        </div>

        @if ($this->logs->isEmpty())
            <x-empty-state icon="clock" title="لا يوجد نشاط مطابق" />
        @else
            <div class="px-4 pb-2">
                <flux:table :paginate="$this->logs">
                    <flux:table.columns>
                        <flux:table.column>الوقت</flux:table.column>
                        <flux:table.column>المستخدم</flux:table.column>
                        <flux:table.column>العملية</flux:table.column>
                        <flux:table.column>الحالة</flux:table.column>
                        <flux:table.column>التفاصيل</flux:table.column>
                        <flux:table.column>IP</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->logs as $log)
                            <flux:table.row :key="$log->id">
                                <flux:table.cell class="ltr-nums" title="{{ $log->created_at->diffForHumans() }}">{{ $log->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $log->actorName() }}</flux:table.cell>
                                <flux:table.cell>{{ $log->label() }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($log->medicalCase && ! $log->medicalCase->trashed())
                                        <a href="{{ route('cases.show', $log->medicalCase) }}" wire:navigate class="ltr-nums text-brand-700 hover:underline">{{ $log->medicalCase->case_code }}</a>
                                    @elseif ($log->medicalCase)
                                        <span class="ltr-nums text-zinc-400 line-through">{{ $log->medicalCase->case_code }}</span>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-w-64 truncate" dir="ltr">{{ $log->meta['name'] ?? (isset($log->meta['fields']) ? implode(', ', $log->meta['fields']) : '') }}</flux:table.cell>
                                <flux:table.cell class="ltr-nums text-xs">{{ $log->ip }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-panel>
</div>
