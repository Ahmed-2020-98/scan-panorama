@props(['icon' => 'inbox', 'title', 'text' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-14 text-center') }}>
    <span class="crop-marks mb-4 flex size-16 items-center justify-center rounded-xl bg-zinc-50 text-zinc-400 [--crop-mark:var(--color-brand-300)]">
        <flux:icon :name="$icon" />
    </span>
    <div class="font-display font-semibold text-zinc-800">{{ $title }}</div>
    @if ($text)
        <p class="mt-1.5 max-w-sm text-sm text-zinc-500">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
