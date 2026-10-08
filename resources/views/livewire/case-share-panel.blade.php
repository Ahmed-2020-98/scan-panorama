<x-panel :title="'مشاركة الحالة '.$case->case_code" icon="share">
    @if($case->isShareActive())
        <flux:input :value="$case->shareUrl()" label="رابط الحالة" readonly dir="ltr" />
        <div class="mt-3 flex flex-wrap gap-2" x-data>
            <flux:button icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($case->shareUrl())).then(() => $wire.markCopied()).catch(() => alert('تعذر النسخ. انسخ الرابط من الحقل.'))">نسخ الرابط</flux:button>
            <a href="{{ $case->whatsappUrl() }}" wire:click="markWhatsapp" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 text-sm font-medium text-brand-700"><x-whatsapp-icon class="size-4" />واتساب الطبيب</a>
            <flux:button :href="$case->shareUrl()" target="_blank" icon="eye">معاينة</flux:button>
        </div>
        <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-3" x-data>
            <div class="text-sm font-medium text-zinc-800">إرسال للمريض</div>
            <p class="mt-0.5 text-xs text-zinc-600">رابط منفصل يعرض الملفات وبيانات الفحص فقط، بدون ملاحظات الطبيب.</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($patientWhatsapp = $case->patientWhatsappUrl())
                    <a href="{{ $patientWhatsapp }}" wire:click="markPatientWhatsapp" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#128C7E] px-3 text-sm font-medium text-white hover:bg-[#0e6f63]"><x-whatsapp-icon class="size-4" />واتساب المريض <span class="ltr-nums text-xs opacity-90">{{ $case->patient->phone }}</span></a>
                @else
                    <span class="inline-flex min-h-11 items-center text-sm text-amber-700">لا يوجد رقم موبايل صحيح للمريض.</span>
                    @can('update', $case->patient)<flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('patients.edit', $case->patient)" wire:navigate>إضافة الرقم</flux:button>@endcan
                @endif
                <flux:button icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($case->patientShareUrl())).then(() => $wire.markPatientCopied()).catch(() => alert('تعذر النسخ.'))">نسخ رابط المريض</flux:button>
                <flux:button :href="$case->patientShareUrl()" target="_blank" icon="eye" variant="ghost">معاينة</flux:button>
            </div>
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
