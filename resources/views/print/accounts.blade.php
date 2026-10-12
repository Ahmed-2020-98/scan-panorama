@php($m = fn ($v) => \App\Support\Money::display((int) $v))
<x-print-sheet title="تقرير الحسابات">
    <p class="muted" style="margin:0">الفترة: <span class="ltr num">{{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}</span> إلى <span class="ltr num">{{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}</span> · {{ $branch?->name ?? 'كل الفروع' }}</p>
    @php($expenseTotal = $expenses?->sum('amount_minor') ?? 0)
    @foreach($summary['currencies'] as $currency => $t)
        <div class="kpis">
            <div class="kpi"><span class="muted">المقبوض</span><b class="num">{{ $m($t['collected']) }}</b></div>
            <div class="kpi"><span class="muted">المتبقي</span><b class="num">{{ $m($t['outstanding'] ?? 0) }}</b></div>
            <div class="kpi"><span class="muted">قيمة الفحوصات</span><b class="num">{{ $m($t['billed']) }}</b></div>
            @if($expenses !== null)<div class="kpi"><span class="muted">صافي (مقبوض − مصروفات)</span><b class="num">{{ $m($t['collected'] - $expenseTotal) }}</b></div>@endif
        </div>
    @endforeach
    <table>
        <tr><th>التاريخ</th><th>الكود</th><th>المريض</th><th>الطبيب</th><th>الفحص</th><th>القيمة</th><th>المقبوض</th><th>المتبقي</th></tr>
        @foreach($cases as $c)
            <tr><td class="num ltr">{{ $c->exam_date->format('d/m') }}</td><td class="num ltr">{{ $c->case_code }}</td><td>{{ $c->patient?->name }}</td><td>{{ $c->doctor?->display_name ?? '—' }}</td><td>{{ $c->examType?->name ?? '—' }}</td><td class="num">{{ $m($c->final_price_minor) }}</td><td class="num">{{ $m($c->payments_sum_amount_minor) }}</td><td class="num">{{ $c->final_price_minor === null ? '—' : $m($c->final_price_minor - (int) $c->payments_sum_amount_minor) }}</td></tr>
        @endforeach
    </table>
    @if($expenses !== null && $expenses->isNotEmpty())
        <h3 style="margin-top:22px">المصروفات</h3>
        <table><tr><th>التاريخ</th><th>البند</th><th>ملاحظة</th><th>المبلغ</th></tr>
            @foreach($expenses as $e)<tr><td class="num ltr">{{ $e->spent_on->format('d/m') }}</td><td>{{ $e->categoryLabel() }}</td><td>{{ $e->note }}</td><td class="num">{{ $m($e->amount_minor) }}</td></tr>@endforeach
            <tr><td colspan="3"><b>الإجمالي</b></td><td class="num"><b>{{ $m($expenseTotal) }}</b></td></tr>
        </table>
    @endif
</x-print-sheet>
