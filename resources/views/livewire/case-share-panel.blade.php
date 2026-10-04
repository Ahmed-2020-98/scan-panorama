<x-panel :title="'مشاركة الحالة '.$case->case_code" icon="share">
    @if($case->isShareActive())
        <flux:input :value="$case->shareUrl()" label="رابط الحالة" readonly dir="ltr" />
        <div class="mt-3 flex flex-wrap gap-2" x-data>
            <flux:button icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($case->shareUrl())).then(() => $wire.markCopied()).catch(() => alert('تعذر النسخ. انسخ الرابط من الحقل.'))">نسخ الرابط</flux:button>
            <a href="{{ $case->whatsappUrl() }}" wire:click="markWhatsapp" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 text-sm font-medium text-brand-700"><x-whatsapp-icon class="size-4" />فتح WhatsApp</a>
            <flux:button :href="$case->shareUrl()" target="_blank" icon="eye">معاينة</flux:button>
        </div>
        <p class="mt-2 text-xs text-zinc-600">{{ $case->share_expires_at ? 'صالح حتى '.$case->share_expires_at->format('d/m/Y') : 'لا ينتهي تلقائيًا' }}. يفتح WhatsApp رسالة جاهزة؛ الإرسال يتم من حسابك.</p>
        <flux:button size="sm" variant="ghost" wire:click="revokeLink" wire:confirm="إلغاء رابط الحالة؟" class="mt-2">إلغاء الرابط</flux:button>
    @else <p class="text-sm text-zinc-600">رابط المشاركة ملغى أو منتهي.</p> @endif
    <flux:button size="sm" variant="ghost" wire:click="regenerateLink" wire:confirm="إنشاء رابط جديد وإلغاء السابق؟">إنشاء رابط جديد</flux:button>
    <form wire:submit="saveSelection" class="mt-4 space-y-3 border-t pt-4">
        <div class="font-medium">الملفات المتاحة داخل الرابط</div>
        @forelse($files as $file)<flux:checkbox wire:model="selected" :value="(string)$file->id" :label="$file->original_name.' — '.$file->type->shortLabel()" />@empty<p class="text-sm text-zinc-500">ارفع ملفات الحالة لتظهر هنا.</p>@endforelse
        <flux:error name="selected" />
        @if($files->isNotEmpty())<flux:button type="submit" size="sm">تحديث الملفات</flux:button>@endif
    </form>
</x-panel>
