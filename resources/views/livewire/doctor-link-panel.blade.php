<x-panel title="رابط حالات الطبيب" icon="link" subtitle="رابط خاص يفتح كل حالات الطبيب وملفاتها بدون تسجيل دخول">
    @if ($doctor->link_token)
        <div x-data class="space-y-3">
            <flux:input :value="$doctor->linkUrl()" readonly dir="ltr" aria-label="رابط حالات الطبيب" />
            <div class="flex flex-wrap gap-2">
                <a href="{{ $doctor->linkWhatsappUrl() }}" wire:click="markSent('whatsapp')" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#128C7E] px-3 text-sm font-medium text-white hover:bg-[#0e6f63]"><x-whatsapp-icon class="size-4" />إرسال للطبيب واتساب</a>
                <flux:button icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($doctor->linkUrl())).then(() => $wire.markSent('copy')).catch(() => alert('تعذر النسخ. انسخ الرابط من الحقل.'))">نسخ الرابط</flux:button>
                <flux:button icon="eye" variant="ghost" :href="$doctor->linkUrl()" target="_blank">معاينة</flux:button>
            </div>
            @unless ($doctor->user->is_active)
                <p class="text-sm text-amber-700">حساب الطبيب معطّل، لذلك الرابط لا يعمل حاليًا.</p>
            @endunless
            <div class="flex flex-wrap gap-2 border-t border-zinc-100 pt-3">
                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="regenerate" wire:confirm="إنشاء رابط جديد؟ الرابط القديم سيتوقف فورًا.">رابط جديد</flux:button>
                <flux:button size="sm" variant="ghost" icon="x-mark" class="text-red-600!" wire:click="revoke" wire:confirm="إلغاء رابط الطبيب؟ لن يفتح بعد الآن.">إلغاء الرابط</flux:button>
            </div>
        </div>
    @else
        <p class="mb-3 text-sm text-zinc-600">لا يوجد رابط لهذا الطبيب. أنشئ رابطًا ثم أرسله له على واتساب ليتابع كل حالاته منه.</p>
        <flux:button variant="primary" icon="link" wire:click="create">إنشاء الرابط</flux:button>
    @endif
</x-panel>
