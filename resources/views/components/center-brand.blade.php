@props(['size' => 'md', 'onFilm' => false])

@php
    $centerName = \App\Models\Setting::get('center_name');
    $tagline = \App\Models\Setting::get('center_tagline');
    $logoUrl = \App\Models\Setting::logoUrl();
    $box = $size === 'lg' ? 'size-14 rounded-2xl' : 'size-11 rounded-xl';
@endphp

<div {{ $attributes->class('flex items-center gap-3') }}>
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $centerName }}" class="{{ $box }} bg-white object-contain p-1 ring-1 {{ $onFilm ? 'ring-white/10' : 'ring-zinc-200' }}">
    @else
        <span @class([
            $box,
            'relative flex shrink-0 items-center justify-center',
            'bg-phosphor text-film-950 shadow-[0_0_24px_-4px_var(--color-phosphor)]' => $onFilm,
            'bg-film-900 text-phosphor shadow-sm ring-1 ring-film-700' => ! $onFilm,
        ])>
            <x-app-logo-icon class="{{ $size === 'lg' ? 'size-8' : 'size-6' }} fill-current" />
        </span>
    @endif
    <div class="min-w-0">
        <div @class([
            'font-display font-bold leading-tight tracking-tight',
            'text-2xl' => $size === 'lg',
            'text-lg' => $size !== 'lg',
            'text-white' => $onFilm,
            'text-zinc-900' => ! $onFilm,
        ])>{{ $centerName }}</div>
        @if ($tagline)
            <div class="text-sm {{ $onFilm ? 'text-zinc-400' : 'text-zinc-500' }}">{{ $tagline }}</div>
        @endif
    </div>
</div>
