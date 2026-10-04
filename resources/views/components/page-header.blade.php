@props(['title', 'subtitle' => null, 'back' => null, 'eyebrow' => null])

<div {{ $attributes->class('mb-7') }}>
    @if ($back)
        <a href="{{ $back }}" wire:navigate class="mb-3 inline-flex items-center gap-1.5 rounded-md text-sm text-zinc-500 transition hover:text-brand-700">
            <flux:icon.arrow-right variant="micro" />
            رجوع
        </a>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            @if ($eyebrow)
                <div class="eyebrow mb-2.5">{{ $eyebrow }}</div>
            @endif
            <h1 class="text-[1.7rem] leading-tight font-bold text-zinc-900 sm:text-3xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1.5 text-sm text-zinc-500">{{ $subtitle }}</p>
            @endif
            {{ $meta ?? '' }}
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    <div class="ruler mt-5"></div>
</div>
