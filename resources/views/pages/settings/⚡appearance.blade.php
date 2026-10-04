<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('المظهر')] class extends Component {
    public string $font = '';

    public function mount(): void
    {
        $this->font = Auth::user()->fontKey();
    }

    public function updatedFont(): void
    {
        $this->validate(['font' => ['required', 'string', Rule::in(array_keys((array) config('radiology.fonts')))]]);

        $user = Auth::user();
        $user->update(['font' => $this->font === config('radiology.default_font') ? null : $this->font]);

        // Apply immediately without a reload; the next page load uses the saved choice.
        $this->js('document.documentElement.style.setProperty("--font-sans", '.json_encode(config("radiology.fonts.{$this->font}.stack").', ui-sans-serif, system-ui, sans-serif').'); document.documentElement.style.setProperty("--font-display", "var(--font-sans)"); document.querySelector("style[data-user-font]")?.remove();');
        Flux::toast(variant: 'success', text: 'تم تغيير الخط.');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="المظهر" subheading="اختر خط الواجهة. يُحفظ لحسابك ويظهر على أي جهاز تدخل منه.">
        <flux:radio.group wire:model.live="font" label="خط الواجهة" variant="cards" class="flex-col">
            @foreach (config('radiology.fonts') as $key => $option)
                <flux:radio :value="$key" class="items-start">
                    <flux:radio.indicator />
                    <div class="flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-sm font-semibold text-zinc-900" dir="ltr">{{ $option['label'] }}</span>
                            <span class="text-xs text-zinc-500">{{ $option['note'] }}@if ($key === config('radiology.default_font')) · الافتراضي @endif</span>
                        </div>
                        <p class="mt-2 text-lg leading-relaxed text-zinc-800" style="font-family: {{ $option['stack'] }}, sans-serif">
                            حالة أشعة بانوراما للمريض، تقرير CBCT جاهز للطبيب
                        </p>
                        <p class="text-sm text-zinc-500" style="font-family: {{ $option['stack'] }}, sans-serif" dir="ltr">Panoramic · CBCT · 2026-00042</p>
                    </div>
                </flux:radio>
            @endforeach
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
