@props(['case', 'type'])

@php
    $config = [
        'url' => route('cases.uploads.store', $case),
        'type' => $type->value,
        'chunkSize' => \App\Support\UploadLimits::chunkSize(),
        'maxBytes' => $type->maxBytes(),
        'extensions' => $type->extensions(),
    ];
@endphp

<div wire:ignore x-data="chunkUploader(@js($config))" {{ $attributes }}>
    <label
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false; pick($event.dataTransfer.files)"
        x-bind:class="dragging ? 'border-brand-500 bg-brand-50' : 'border-zinc-300 bg-zinc-50 hover:border-brand-400 hover:bg-brand-50/50'"
        class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-5 text-center transition"
    >
        <flux:icon.cloud-arrow-up class="text-brand-600" />
        <span class="text-sm font-medium text-zinc-700">اسحب الملفات هنا أو <span class="text-brand-700 underline">اختر من الجهاز</span></span>
        <span class="text-xs text-zinc-500">
            <span class="ltr-nums">{{ strtoupper(implode(' · ', $type->extensions())) }}</span>
            &middot; حتى {{ \Illuminate\Support\Number::fileSize($type->maxBytes()) }}
        </span>
        <input x-ref="input" type="file" multiple class="sr-only" accept="{{ $type->accept() }}" x-on:change="pick($event.target.files)">
    </label>

    <ul x-show="items.length" x-cloak class="mt-3 space-y-2">
        <template x-for="item in items" x-bind:key="item.key">
            <li class="rounded-lg border border-zinc-200 bg-white px-3 py-2">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="truncate font-medium text-zinc-800" x-text="item.name"></span>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="ltr-nums text-xs text-zinc-500" x-text="formatSize(item.size)"></span>
                        <template x-if="item.status === 'uploading' || item.status === 'queued'">
                            <button type="button" x-on:click="cancel(item)" class="text-xs text-zinc-500 hover:text-red-600">إلغاء</button>
                        </template>
                        <template x-if="item.status === 'error' || item.status === 'cancelled'">
                            <button type="button" x-on:click="retry(item)" class="min-h-11 px-2 text-xs text-brand-700">إعادة المحاولة</button>
                            <button type="button" x-on:click="remove(item)" class="text-xs text-zinc-500 hover:text-zinc-800">إخفاء</button>
                        </template>
                    </span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100" x-show="item.status !== 'error' && item.status !== 'cancelled'">
                    <div class="h-full rounded-full transition-all" x-bind:class="item.status === 'done' ? 'bg-emerald-500' : 'bg-brand-500'" x-bind:style="`width: ${item.progress}%`"></div>
                </div>
                <div class="mt-1 text-xs" x-bind:class="item.status === 'error' || item.status === 'cancelled' ? 'text-red-600' : 'text-zinc-500'" x-text="statusText(item)"></div>
            </li>
        </template>
    </ul>
</div>
