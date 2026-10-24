@props(['images' => [], 'name' => 'case-images'])
{{-- WhatsApp-style grid of a case's images; a tap opens <x-image-slider> with the same name at that image. --}}
@if (count($images))
<div {{ $attributes->class('grid grid-cols-3 gap-1 sm:grid-cols-4') }} x-data data-test="image-gallery">
    @foreach (array_values($images) as $idx => [$url, $label])
        <button type="button" x-on:click="$dispatch('open-slider-{{ $name }}', { index: {{ $idx }} })" class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-md bg-zinc-900" aria-label="عرض {{ $label }}">
            <img src="{{ $url }}" alt="" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
        </button>
    @endforeach
</div>
@endif
