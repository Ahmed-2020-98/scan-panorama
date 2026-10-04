@props(['label', 'value', 'icon', 'tone' => 'brand', 'hint' => null, 'href' => null])

@php
    $tones = [
        'brand' => ['bar' => 'bg-brand-500', 'icon' => 'text-brand-600'],
        'amber' => ['bar' => 'bg-marker', 'icon' => 'text-amber-600'],
        'sky' => ['bar' => 'bg-sky-500', 'icon' => 'text-sky-600'],
        'green' => ['bar' => 'bg-emerald-500', 'icon' => 'text-emerald-600'],
        'indigo' => ['bar' => 'bg-indigo-500', 'icon' => 'text-indigo-600'],
        'zinc' => ['bar' => 'bg-zinc-400', 'icon' => 'text-zinc-500'],
    ];
    $palette = $tones[$tone] ?? $tones['brand'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class([
    'group relative block overflow-hidden rounded-xl border border-zinc-200/90 bg-white px-5 pt-4 pb-3.5 shadow-[0_1px_0_rgb(8_30_70/0.03),0_12px_32px_-24px_rgb(8_30_70/0.35)]',
    'transition duration-200 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-[0_18px_40px_-24px_rgb(8_30_70/0.45)]' => $href,
]) }}>
    <span class="absolute inset-y-4 start-0 w-[3px] rounded-e-full {{ $palette['bar'] }}"></span>

    <div class="flex items-start justify-between gap-3">
        <span class="text-sm text-zinc-500">{{ $label }}</span>
        <flux:icon :name="$icon" variant="mini" class="{{ $palette['icon'] }} opacity-80 transition group-hover:opacity-100" />
    </div>

    <div class="mt-2 text-[2.1rem] leading-none font-medium text-zinc-900">
        <span class="ltr-nums">{{ $value }}</span>
    </div>

    <div class="ruler mt-4 opacity-70"></div>
    <div class="mt-2 flex items-center justify-between text-xs text-zinc-400">
        <span>{{ $hint ?? ' ' }}</span>
        @if ($href)
            <flux:icon.arrow-up-left variant="micro" class="opacity-0 transition group-hover:opacity-100" />
        @endif
    </div>
</{{ $tag }}>
