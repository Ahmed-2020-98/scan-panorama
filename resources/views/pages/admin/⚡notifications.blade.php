<?php
use Livewire\Attributes\{Computed,Title};
use Livewire\Component;
use Livewire\WithPagination;
new #[Title('التنبيهات')] class extends Component {
    use WithPagination;
    #[Computed] public function items() { return auth()->user()->notifications()->latest()->paginate(30); }
    public function open(string $id) {
        $n=auth()->user()->notifications()->findOrFail($id); $n->markAsRead();
        $url=$n->data['url'] ?? null;
        return $url && str_starts_with($url, url('/')) ? $this->redirect($url, navigate: true) : null;
    }
    public function readAll(): void { auth()->user()->unreadNotifications->markAsRead(); unset($this->items); }
}; ?>
<div class="mx-auto w-full max-w-3xl space-y-5">
    <x-page-header title="التنبيهات" subtitle="أطباء قلّلوا أو وقفوا الحالات، زيارات مستحقة، وطلبات الدفاتر">
        <x-slot:actions>@if(auth()->user()->unreadNotifications()->exists())<flux:button icon="check" wire:click="readAll">تعليم الكل كمقروء</flux:button>@endif</x-slot:actions>
    </x-page-header>
    <x-panel :padded="false">
        <div class="divide-y">
        @forelse($this->items as $n)
            @php($kind = $n->data['kind'] ?? '')
            <button type="button" wire:click="open('{{ $n->id }}')" class="flex w-full items-start gap-3 p-4 text-start transition hover:bg-zinc-50 {{ $n->read_at ? '' : 'bg-brand-50/50' }}" wire:key="n-{{ $n->id }}">
                <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full {{ match($kind) { 'stopped' => 'bg-red-100 text-red-600', 'declining' => 'bg-amber-100 text-amber-700', 'booklet' => 'bg-violet-100 text-violet-700', default => 'bg-brand-100 text-brand-700' } }}">
                    <flux:icon :name="match($kind) { 'stopped' => 'no-symbol', 'declining' => 'arrow-trending-down', 'booklet' => 'book-open', default => 'calendar-days' }" variant="mini" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium {{ $n->read_at ? 'text-zinc-700' : 'text-zinc-900' }}">{{ $n->data['title'] ?? '' }}</span><span class="text-xs text-zinc-500">{{ $n->created_at->diffForHumans() }}</span></span>
                    <span class="mt-1 block text-sm text-zinc-600">{{ $n->data['body'] ?? '' }}</span>
                </span>
                @unless($n->read_at)<span class="mt-2 size-2 shrink-0 rounded-full bg-brand-600" aria-label="غير مقروء"></span>@endunless
            </button>
        @empty
            <x-empty-state icon="bell" title="لا توجد تنبيهات" text="ستظهر هنا تنبيهات الأطباء والزيارات والدفاتر." />
        @endforelse
        </div>
        <div class="p-4">{{ $this->items->links() }}</div>
    </x-panel>
</div>
