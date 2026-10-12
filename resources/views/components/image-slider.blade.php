@props(['images' => [], 'name' => 'case-images'])
{{-- Full-screen slider for a case's X-ray images. Open with: $dispatch('open-slider-{{ name }}', { index }) --}}
@if (count($images))
<div
    x-data="{
        open: false, i: 0, zoom: false, startX: null,
        images: @js(array_values($images)),
        show(index) { this.i = index; this.zoom = false; this.open = true; document.body.style.overflow = 'hidden'; this.$nextTick(() => this.$refs.close.focus()); },
        close() { this.open = false; document.body.style.overflow = ''; },
        next() { this.zoom = false; this.i = (this.i + 1) % this.images.length; },
        prev() { this.zoom = false; this.i = (this.i - 1 + this.images.length) % this.images.length; },
    }"
    x-on:open-slider-{{ $name }}.window="show($event.detail.index ?? 0)"
    x-on:keydown.escape.window="open && close()"
    x-on:keydown.arrow-left.window="open && next()"
    x-on:keydown.arrow-right.window="open && prev()"
>
    <div x-show="open" x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex flex-col bg-[#040b19]/95 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="عرض الصور" style="display: none"
        x-on:touchstart="startX = $event.touches[0].clientX"
        x-on:touchend="if (startX !== null && ! zoom) { const d = $event.changedTouches[0].clientX - startX; if (Math.abs(d) > 50) { d < 0 ? next() : prev(); } } startX = null">
        <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm text-zinc-300">
            <span class="ltr-nums" x-text="(i + 1) + ' / ' + images.length"></span>
            <span class="min-w-0 truncate" dir="ltr" x-text="images[i]?.[1]"></span>
            <div class="flex items-center gap-1">
                <a :href="images[i]?.[2]" class="rounded-lg p-2 hover:bg-white/10" aria-label="تحميل"><flux:icon.arrow-down-tray class="size-5" /></a>
                <button type="button" x-on:click="zoom = ! zoom" class="rounded-lg p-2 hover:bg-white/10" :aria-label="zoom ? 'تصغير' : 'تكبير'"><flux:icon.magnifying-glass-plus class="size-5" /></button>
                <button type="button" x-ref="close" x-on:click="close()" class="rounded-lg p-2 hover:bg-white/10" aria-label="إغلاق"><flux:icon.x-mark class="size-6" /></button>
            </div>
        </div>
        <div class="relative flex min-h-0 flex-1 items-center justify-center" :class="zoom ? 'overflow-auto' : 'overflow-hidden'">
            <template x-for="(img, idx) in images" :key="idx">
                <img x-show="idx === i" :src="idx === i || Math.abs(idx - i) === 1 ? img[0] : ''" :alt="img[1]" x-on:dblclick="zoom = ! zoom"
                    class="select-none transition-transform duration-200" :class="zoom ? 'max-w-none cursor-zoom-out' : 'max-h-full max-w-full cursor-zoom-in object-contain p-2'">
            </template>
            <template x-if="images.length > 1">
                <div>
                    <button type="button" x-on:click="prev()" class="absolute start-2 top-1/2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="السابقة"><flux:icon.chevron-right class="size-6" /></button>
                    <button type="button" x-on:click="next()" class="absolute end-2 top-1/2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="التالية"><flux:icon.chevron-left class="size-6" /></button>
                </div>
            </template>
        </div>
        <div x-show="images.length > 1" class="flex justify-center gap-2 overflow-x-auto px-4 py-3">
            <template x-for="(img, idx) in images" :key="'t' + idx">
                <button type="button" x-on:click="i = idx; zoom = false" class="h-14 w-20 shrink-0 overflow-hidden rounded-md border-2 transition" :class="idx === i ? 'border-phosphor' : 'border-transparent opacity-60 hover:opacity-100'">
                    <img :src="img[0]" loading="lazy" alt="" class="size-full object-cover">
                </button>
            </template>
        </div>
    </div>
</div>
@endif
