@props(['label'])

<div {{ $attributes }}>
    <dt class="text-xs text-zinc-500">{{ $label }}</dt>
    <dd class="mt-0.5 font-medium text-zinc-900">{{ $slot->isEmpty() ? '—' : $slot }}</dd>
</div>
