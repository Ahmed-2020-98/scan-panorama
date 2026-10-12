<x-print-sheet title="إيصال" paper="receipt">
    <table>
        <tr><td class="muted">المريض</td><td>{{ $case->patient->name }}</td></tr>
        <tr><td class="muted">الطبيب</td><td>{{ $case->doctor?->display_name ?? '—' }}</td></tr>
        <tr><td class="muted">التاريخ</td><td class="ltr num">{{ $case->exam_date->format('d/m/Y') }}</td></tr>
    </table>
    <div class="barcode" style="margin-top:12px">{!! \App\Support\Barcode::svg($case->case_code, 40, 1.2) !!}<span>{{ $case->case_code }}</span></div>
</x-print-sheet>
