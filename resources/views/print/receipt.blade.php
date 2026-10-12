@php($m = fn ($v) => \App\Support\Money::display($v))
<x-print-sheet title="إيصال" paper="receipt">
    <table>
        <tr><td class="muted">رقم الحالة</td><td class="ltr num">{{ $case->case_code }}</td></tr>
        <tr><td class="muted">المريض</td><td>{{ $case->patient->name }}</td></tr>
        @if($case->patient->phone)<tr><td class="muted">الموبايل</td><td class="ltr num">{{ $case->patient->phone }}</td></tr>@endif
        <tr><td class="muted">الفحص</td><td>{{ $case->examType?->name ?? '—' }}</td></tr>
        <tr><td class="muted">الطبيب</td><td>{{ $case->doctor?->display_name ?? '—' }}</td></tr>
        <tr><td class="muted">التاريخ</td><td class="ltr num">{{ $case->exam_date->format('d/m/Y') }}</td></tr>
        <tr><td class="muted">الفرع</td><td>{{ $case->branch->name }}</td></tr>
    </table>
    @if($case->payments->isNotEmpty())
        <table>
            <tr><th>الدفعة</th><th>المبلغ</th></tr>
            @foreach($case->payments as $p)
                <tr><td class="num">{{ $p->received_at->format('d/m H:i') }} · {{ ['cash' => 'نقدًا', 'card' => 'بطاقة', 'transfer' => 'تحويل'][$p->method] ?? $p->method }}</td><td class="num">{{ $m($p->amount_minor) }}</td></tr>
            @endforeach
        </table>
    @endif
    <div class="total"><span>قيمة الفحص</span><span class="num">{{ $m($case->final_price_minor) }} {{ $case->currency }}</span></div>
    <div style="display:flex;justify-content:space-between;margin-top:4px"><span>المدفوع</span><span class="num">{{ $m($paid) }}</span></div>
    <div style="display:flex;justify-content:space-between;font-weight:700"><span>المتبقي</span><span class="num">{{ $case->final_price_minor === null ? '—' : $m($case->final_price_minor - $paid) }}</span></div>
    <div class="barcode" style="margin-top:12px">{!! \App\Support\Barcode::svg($case->case_code, 40, 1.2) !!}<span>{{ $case->case_code }}</span></div>
    <p class="muted" style="text-align:center;margin-top:8px">شكرًا لثقتكم — نتمنى لكم دوام الصحة</p>
</x-print-sheet>
