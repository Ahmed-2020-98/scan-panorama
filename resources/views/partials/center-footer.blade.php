@php
    $contactPhone = \App\Models\Setting::get('contact_phone');
    $supportWhatsapp = \App\Models\Setting::get('support_whatsapp');
@endphp

<footer class="border-t border-zinc-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-5 text-sm sm:px-6">
        <div class="text-zinc-500">&copy; {{ now()->year }} {{ \App\Models\Setting::get('center_name') }}</div>
        <div class="flex flex-wrap items-center gap-3">
            @if ($contactPhone)
                <a href="tel:{{ $contactPhone }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-zinc-700 hover:bg-zinc-100">
                    <flux:icon.phone variant="mini" class="text-brand-600" />
                    للتواصل: <span class="ltr-nums font-semibold">{{ $contactPhone }}</span>
                </a>
            @endif
            @if ($supportWhatsapp)
                <a href="{{ \App\Support\Phone::whatsappUrl($supportWhatsapp) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-3 py-2 font-medium text-white hover:bg-[#1ebe5b]">
                    <x-whatsapp-icon class="size-4" />
                    الدعم الفني
                </a>
            @endif
        </div>
    </div>
</footer>
