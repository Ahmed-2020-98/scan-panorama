@props([
    'sidebar' => false,
])

@php
    $centerName = \App\Models\Setting::get('center_name');
    $logoUrl = \App\Models\Setting::logoUrl();
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$centerName" {{ $attributes->class('font-display') }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg {{ $logoUrl ? 'bg-white' : 'bg-phosphor text-film-950 shadow-[0_0_18px_-4px_var(--color-phosphor)]' }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="" class="size-8 object-contain">
            @else
                <x-app-logo-icon class="size-5 fill-current" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$centerName" {{ $attributes->class('font-display') }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg {{ $logoUrl ? 'bg-white' : 'bg-phosphor text-film-950' }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="" class="size-8 object-contain">
            @else
                <x-app-logo-icon class="size-5 fill-current" />
            @endif
        </x-slot>
    </flux:brand>
@endif
