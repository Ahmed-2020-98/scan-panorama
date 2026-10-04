@props(['title' => null, 'subtitle' => null, 'icon' => null, 'padded' => true])

<section {{ $attributes->class('overflow-hidden rounded-xl border border-zinc-200/90 bg-white shadow-[0_1px_0_rgb(8_30_70/0.03),0_12px_32px_-24px_rgb(8_30_70/0.35)]') }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3.5">
            <div class="flex min-w-0 items-center gap-3">
                @if ($icon)
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 text-brand-600">
                        <flux:icon :name="$icon" variant="mini" />
                    </span>
                @endif
                <div class="min-w-0">
                    <h2 class="text-[0.95rem] font-semibold text-zinc-900">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="text-xs text-zinc-500">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif

    <div @class(['p-5' => $padded])>
        {{ $slot }}
    </div>
</section>
