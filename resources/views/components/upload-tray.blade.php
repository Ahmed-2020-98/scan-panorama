{{-- Floating progress of the global upload queue; stays on screen across pages. --}}
<div x-data x-show="$store.uploads.items.length" x-cloak class="fixed bottom-4 end-4 z-50 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl" role="status" aria-live="polite">
    <button type="button" x-on:click="$store.uploads.open = ! $store.uploads.open" class="flex w-full items-center gap-3 bg-zinc-50 px-4 py-3 text-start">
        <flux:icon.cloud-arrow-up variant="mini" class="shrink-0 text-brand-600" />
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-zinc-800" x-text="$store.uploads.active.length ? `جارٍ رفع ${$store.uploads.active.length} ملف · ${$store.uploads.percent}%` : 'انتهى الرفع'"></span>
            <span class="block text-xs text-zinc-500">
                تم <span class="ltr-nums" x-text="$store.uploads.done.length"></span>
                · متبقي <span class="ltr-nums" x-text="$store.uploads.active.length"></span>
                <template x-if="$store.uploads.failed.length"><span class="text-red-600">· فشل <span class="ltr-nums" x-text="$store.uploads.failed.length"></span></span></template>
            </span>
        </span>
        <flux:icon.chevron-down variant="mini" class="shrink-0 text-zinc-400 transition" x-bind:class="$store.uploads.open ? '' : 'rotate-180'" />
    </button>
    <div class="h-1 bg-zinc-100" x-show="$store.uploads.active.length"><div class="h-full bg-brand-500 transition-all" x-bind:style="`width: ${$store.uploads.percent}%`"></div></div>

    <div x-show="$store.uploads.open" x-collapse>
        <ul class="max-h-72 divide-y divide-zinc-100 overflow-y-auto">
            <template x-for="item in $store.uploads.items" x-bind:key="item.key">
                <li class="px-4 py-2.5">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate font-medium text-zinc-800" x-text="item.name"></span>
                        <span class="shrink-0">
                            <template x-if="item.status === 'uploading' || item.status === 'queued'"><button type="button" x-on:click="$store.uploads.cancel(item)" class="text-xs text-zinc-500 hover:text-red-600">إلغاء</button></template>
                            <template x-if="(item.status === 'error' || item.status === 'cancelled') && item.file"><button type="button" x-on:click="$store.uploads.retry(item)" class="text-xs text-brand-700">إعادة المحاولة</button></template>
                        </span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between gap-2 text-xs text-zinc-500">
                        <a x-bind:href="item.caseUrl" wire:navigate class="ltr-nums text-brand-700 hover:underline" x-text="`${item.caseCode} · ${item.typeLabel}`"></a>
                        <span class="ltr-nums" x-text="$store.uploads.formatSize(item.size)"></span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-zinc-100" x-show="item.status !== 'error' && item.status !== 'cancelled'">
                        <div class="h-full rounded-full transition-all" x-bind:class="item.status === 'done' ? 'bg-emerald-500' : 'bg-brand-500'" x-bind:style="`width: ${item.progress}%`"></div>
                    </div>
                    <div class="mt-1 text-xs" x-bind:class="item.status === 'error' || item.status === 'cancelled' ? 'text-red-600' : 'text-zinc-500'" x-text="$store.uploads.statusText(item)"></div>
                </li>
            </template>
        </ul>
        <div class="flex items-center justify-between gap-2 border-t border-zinc-100 px-4 py-2">
            <span class="text-xs text-zinc-500" x-show="$store.uploads.active.length">تقدر تكمل شغلك في صفحات تانية، بس ما تقفلش التاب.</span>
            <span class="flex gap-3 ms-auto">
                <button type="button" x-show="$store.uploads.failed.some((item) => item.file)" x-on:click="$store.uploads.retryFailed()" class="text-xs font-medium text-brand-700">إعادة الفاشلة</button>
                <button type="button" x-show="$store.uploads.items.length > $store.uploads.active.length" x-on:click="$store.uploads.clearFinished()" class="text-xs text-zinc-500 hover:text-zinc-800">مسح المنتهية</button>
            </span>
        </div>
    </div>
</div>
