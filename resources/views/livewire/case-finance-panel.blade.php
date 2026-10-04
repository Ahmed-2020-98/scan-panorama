<x-panel title="حسابات الحالة" icon="banknotes">
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-zinc-500">السعر الأساسي</dt><dd class="mt-1 font-semibold">{{ \App\Support\Money::display($case->base_price_minor) }}</dd></div>
        <div><dt class="text-zinc-500">الخصم</dt><dd class="mt-1">{{ \App\Support\Money::display($case->discount_minor) }}</dd></div>
        <div><dt class="text-zinc-500">السعر النهائي</dt><dd class="mt-1 font-semibold">{{ \App\Support\Money::display($case->final_price_minor) }} {{ $case->currency }}</dd></div>
        <div><dt class="text-zinc-500">المقبوض</dt><dd class="mt-1">{{ \App\Support\Money::display($collected) }}</dd></div>
        <div><dt class="text-zinc-500">المتبقي</dt><dd class="mt-1 font-semibold">{{ $case->final_price_minor === null ? 'غير مسعّرة' : \App\Support\Money::display($case->final_price_minor-$collected) }}</dd></div>
    </dl>
    @if($case->discount_reason)<p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm">{{ $case->discount_reason }}</p>@endif
    @if($case->final_price_minor !== null)<form wire:submit="receive" class="mt-5 space-y-3"><flux:input wire:model="amount" label="مبلغ التحصيل" inputmode="decimal" /><flux:error name="price" /><flux:select wire:model="method" label="طريقة الدفع"><flux:select.option value="cash">نقدًا</flux:select.option><flux:select.option value="card">بطاقة</flux:select.option><flux:select.option value="transfer">تحويل</flux:select.option></flux:select><flux:button type="submit" variant="primary" class="w-full">تسجيل التحصيل</flux:button></form>@endif
</x-panel>
