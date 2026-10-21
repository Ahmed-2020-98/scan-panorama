<?php
use App\Models\{Campaign,CampaignRecipient};
use App\Support\{AccessScope,Phone};
use Livewire\Attributes\{Computed,Title};
use Livewire\Component;
new #[Title('إرسال الرسائل')] class extends Component {
    public Campaign $campaign;
    public string $show='pending';
    public function mount(Campaign $campaign): void {
        abort_unless(auth()->user()->isAdmin() || $campaign->created_by===auth()->id() || in_array($campaign->branch_id, AccessScope::branchIds(auth()->user()), true), 403);
    }
    #[Computed] public function recipients() { return $this->campaign->recipients()->when($this->show==='pending',fn($q)=>$q->whereNull('sent_at'))->when($this->show==='sent',fn($q)=>$q->whereNotNull('sent_at'))->orderBy('id')->limit(300)->get(); }
    #[Computed] public function stats(): array { $q=$this->campaign->recipients(); return ['total'=>(clone $q)->count(),'sent'=>(clone $q)->whereNotNull('sent_at')->count()]; }
    #[Computed] public function next(): ?CampaignRecipient { return $this->campaign->recipients()->whereNull('sent_at')->orderBy('id')->first(); }
    public function markSent(int $id): void { $this->campaign->recipients()->whereKey($id)->whereNull('sent_at')->update(['sent_at'=>now(),'sent_by'=>auth()->id()]); unset($this->recipients,$this->stats,$this->next); }
    public function undo(int $id): void { $this->campaign->recipients()->whereKey($id)->update(['sent_at'=>null,'sent_by'=>null]); unset($this->recipients,$this->stats,$this->next); }
}; ?>
<div class="mx-auto w-full max-w-4xl space-y-5" x-data="campaignSender" x-on:keydown.enter.window="if (! ['INPUT','TEXTAREA','BUTTON','A'].includes($event.target.tagName)) { $event.preventDefault(); $refs.next?.click(); }">
    <x-page-header eyebrow="رسائل العروض" :title="$campaign->title" :subtitle="Campaign::AUDIENCES[$campaign->audience].' · '.$campaign->created_at->format('d/m/Y')" :back="route('campaigns.index')" />
    @php($pct = $this->stats['total'] ? (int) floor($this->stats['sent'] * 100 / $this->stats['total']) : 0)
    <x-panel>
        <div class="flex flex-wrap items-center justify-between gap-3"><span class="text-sm">تم إرسال <strong class="ltr-nums">{{ $this->stats['sent'] }}</strong> من <strong class="ltr-nums">{{ $this->stats['total'] }}</strong></span><span class="text-sm text-zinc-500 ltr-nums">{{ $pct }}%</span></div>
        <div class="mt-2 h-3 rounded-full bg-zinc-100"><div class="h-3 rounded-full bg-brand-600 transition-all" style="width: {{ $pct }}%"></div></div>
        @if($n = $this->next)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-brand-200 bg-brand-50 p-4">
                <div><div class="text-xs text-brand-700">التالي</div><div class="text-lg font-semibold">{{ $n->name }}</div><div class="text-sm text-zinc-600 ltr-nums" dir="ltr">{{ $n->phone }}</div></div>
                <a href="{{ Phone::whatsappUrl($n->phone, $campaign->messageFor($n->name)) }}" x-on:click="$el.href = link($el.dataset.phone, $el.dataset.text); $el.target = target" data-phone="{{ Phone::toInternational($n->phone) }}" data-text="{{ $campaign->messageFor($n->name) }}" rel="noopener" wire:click="markSent({{ $n->id }})" x-ref="next" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-[#128C7E] px-5 text-base font-semibold text-white shadow-sm hover:bg-[#0e6f63]"><x-whatsapp-icon class="size-5" /> إرسال والتالي</a>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span class="text-zinc-600">يفتح في:</span>
                <label class="inline-flex items-center gap-1.5"><input type="radio" value="app" x-model="mode" class="accent-brand-600"> تطبيق واتساب (أسرع)</label>
                <label class="inline-flex items-center gap-1.5"><input type="radio" value="web" x-model="mode" class="accent-brand-600"> واتساب ويب</label>
            </div>
            <p class="mt-2 text-xs text-zinc-500">اضغط <kbd class="rounded border bg-white px-1">Enter</kbd> أو الزرار: واتساب يفتح على الشخص التالي والرسالة جاهزة، اضغط إرسال هناك وارجع اضغط Enter تاني. الإرسال من رقمك وبدون اشتراك.@if($campaign->image_path) بعد ما المحادثة تفتح الصق الصورة (Ctrl+V) قبل الإرسال.@endif</p>
        @else
            <flux:callout class="mt-5" color="green" icon="check-circle"><flux:callout.text>تم الإرسال للجميع.</flux:callout.text></flux:callout>
        @endif
    </x-panel>
    @if($url = $campaign->imageUrl())
        <x-panel title="الصورة" icon="photo">
            <div class="flex flex-wrap items-start gap-4">
                <img src="{{ $url }}" alt="" class="max-h-48 rounded-lg border object-contain">
                <div class="space-y-2">
                    <flux:button icon="clipboard-document" x-on:click="copyImage('{{ $url }}')"><span x-text="copied ? 'اتنسخت ✓' : 'نسخ الصورة'"></span></flux:button>
                    <flux:button icon="arrow-down-tray" :href="$url" download>تحميل الصورة</flux:button>
                    <p class="max-w-xs text-xs text-zinc-500">على الكمبيوتر: انسخ الصورة مرة واحدة، والصقها (Ctrl+V) في كل محادثة. على الموبايل: نزّلها مرة وابعتها من المعرض.</p>
                </div>
            </div>
        </x-panel>
    @endif
    <x-panel title="الرسالة"><p class="whitespace-pre-line text-sm leading-relaxed">{{ $campaign->message }}</p></x-panel>
    <x-panel :padded="false">
        <div class="flex gap-2 border-b border-zinc-100 p-3">@foreach(['pending'=>'لم تُرسل','sent'=>'تم الإرسال','all'=>'الكل'] as $k=>$l)<flux:button size="sm" :variant="$show===$k ? 'primary' : 'ghost'" wire:click="$set('show','{{ $k }}')">{{ $l }}</flux:button>@endforeach</div>
        <div class="divide-y">
        @forelse($this->recipients as $r)
            <div class="flex items-center gap-3 p-3" wire:key="r-{{ $r->id }}">
                <div class="min-w-0 flex-1"><div class="truncate font-medium">{{ $r->name }}</div><div class="text-xs text-zinc-500 ltr-nums" dir="ltr">{{ $r->phone }}</div></div>
                @if($r->sent_at)
                    <flux:badge size="sm" color="green">أُرسلت {{ $r->sent_at->format('H:i') }}</flux:badge><flux:button size="xs" variant="ghost" wire:click="undo({{ $r->id }})">تراجع</flux:button>
                @else
                    <a href="{{ Phone::whatsappUrl($r->phone, $campaign->messageFor($r->name)) }}" x-on:click="$el.href = link($el.dataset.phone, $el.dataset.text); $el.target = target" data-phone="{{ Phone::toInternational($r->phone) }}" data-text="{{ $campaign->messageFor($r->name) }}" rel="noopener" wire:click="markSent({{ $r->id }})" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-[#128C7E] px-3 text-sm font-medium text-white hover:bg-[#0e6f63]"><x-whatsapp-icon class="size-4" /> إرسال</a>
                @endif
            </div>
        @empty<p class="p-4 text-sm text-zinc-500">لا يوجد.</p>@endforelse
        </div>
    </x-panel>
</div>
