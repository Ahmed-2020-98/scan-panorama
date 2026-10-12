@props(['options' => [], 'label' => null, 'placeholder' => 'اكتب للبحث…', 'empty' => 'يحدد لاحقًا', 'model'])
{{-- Type-to-filter picker bound to a Livewire property. $options: list of [value, label, hint]. --}}
<div
    x-data="{
        open: false, q: '', active: 0,
        value: $wire.entangle(@js($model)).live,
        options: @js(collect($options)->map(fn ($o) => ['v' => (string) $o[0], 'l' => $o[1], 'h' => $o[2] ?? ''])->values()),
        norm(s) { return (s || '').toString().toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').replace(/^(د|ا\.د|ا\.م\.د)\.?\s*/, ''); },
        get filtered() { const n = this.norm(this.q).trim(); return n === '' ? this.options : this.options.filter(o => this.norm(o.l).includes(n) || this.norm(o.h).includes(n) || this.norm(o.l).split(/\s+/).some(w => w.startsWith(n))); },
        get selected() { return this.options.find(o => o.v === String(this.value ?? '')); },
        pick(o) { this.value = o ? o.v : ''; this.open = false; this.q = ''; },
        move(d) { const n = this.filtered.length; if (! n) return; this.active = (this.active + d + n) % n; this.$nextTick(() => this.$refs.list?.children[this.active]?.scrollIntoView({ block: 'nearest' })); },
    }"
    x-on:click.outside="open = false"
    class="relative"
    {{ $attributes->only('class') }}
>
    @if ($label)<label class="mb-2 block text-sm font-medium text-zinc-800">{{ $label }}</label>@endif
    <div class="relative">
        <input type="text" x-ref="input" autocomplete="off" role="combobox" :aria-expanded="open"
            class="block h-10 w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white ps-9 pe-9 text-base shadow-xs placeholder-zinc-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 sm:text-sm"
            :placeholder="selected ? selected.l : @js($placeholder)"
            :class="selected && ! open ? 'placeholder-zinc-800' : ''"
            x-model="q" x-on:focus="open = true; active = 0" x-on:input="open = true; active = 0"
            x-on:keydown.arrow-down.prevent="open = true; move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="if (open && filtered[active]) pick(filtered[active])" x-on:keydown.escape="open = false">
        <flux:icon.magnifying-glass variant="mini" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-zinc-400" />
        <button type="button" x-show="selected" x-on:click="pick(null); $refs.input.focus()" class="absolute end-2 top-1/2 -translate-y-1/2 rounded p-1 text-zinc-400 hover:text-zinc-700" aria-label="مسح الاختيار"><flux:icon.x-mark variant="mini" /></button>
    </div>
    <ul x-show="open" x-transition.opacity.duration.100ms x-ref="list" role="listbox" class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg" style="display: none">
        <template x-for="(o, i) in filtered" :key="o.v">
            <li role="option" :aria-selected="o.v === String(value)" x-on:click="pick(o)" x-on:mouseenter="active = i"
                class="flex min-h-10 cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2 text-sm"
                :class="i === active ? 'bg-brand-50 text-brand-800' : 'text-zinc-800'">
                <span x-text="o.l"></span><span class="text-xs text-zinc-500 ltr-nums" x-text="o.h"></span>
            </li>
        </template>
        <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-zinc-500">لا يوجد تطابق</li>
        <li x-show="q === '' && selected" x-on:click="pick(null)" class="cursor-pointer rounded-md px-3 py-2 text-sm text-zinc-500 hover:bg-zinc-50">{{ $empty }}</li>
    </ul>
</div>
