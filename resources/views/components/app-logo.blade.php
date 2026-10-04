@props([
    'sidebar' => false,
])

@php
    $centerName = \App\Models\Setting::get('center_name');
    $logoUrl = \App\Models\Setting::logoUrl();
@endphp

{{-- Always rendered on the navy sidebar/header, so the built-in logo uses its dark-surface variant. --}}
<a {{ $attributes->class(['flex shrink-0 items-center rounded-lg focus-visible:outline-phosphor', 'px-1 py-1' => $sidebar]) }}>
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $centerName }}" class="{{ $sidebar ? 'h-12' : 'h-9' }} w-auto max-w-44 rounded-md bg-white object-contain p-1">
    @else
        <img src="{{ asset('images/brand/logo-dark.png') }}" alt="{{ $centerName }}" class="{{ $sidebar ? 'h-20' : 'h-10' }} w-auto">
    @endif
</a>
