@props(['size' => 'md', 'onFilm' => false])

@php
    $centerName = \App\Models\Setting::get('center_name');
    $tagline = \App\Models\Setting::get('center_tagline');
    $logoUrl = \App\Models\Setting::logoUrl();
    $height = $size === 'lg' ? 'h-20' : 'h-14';
@endphp

<div {{ $attributes->class('flex flex-col items-start gap-2') }}>
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $centerName }}" class="{{ $height }} w-auto max-w-60 object-contain {{ $onFilm ? 'rounded-lg bg-white p-1.5' : '' }}">
    @else
        <img src="{{ asset($onFilm ? 'images/brand/logo-dark.png' : 'images/brand/logo.png') }}" alt="{{ $centerName }}" class="{{ $height }} w-auto">
    @endif
    @if ($tagline)
        <div class="text-sm font-medium {{ $onFilm ? 'text-zinc-300' : 'text-zinc-600' }}">{{ $tagline }}</div>
    @endif
</div>
