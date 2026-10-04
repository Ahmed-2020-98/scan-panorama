@props([
    'title',
    'description',
])

<div class="flex w-full flex-col">
    <h1 class="font-display text-3xl font-bold text-zinc-900">{{ $title }}</h1>
    <p class="mt-2 text-sm text-zinc-500">{{ $description }}</p>
</div>
