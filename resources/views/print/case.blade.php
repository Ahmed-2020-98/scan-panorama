<x-print-sheet title="بيانات الحالة">
    <div class="codes" style="margin:-6px 0 14px">
        <div class="barcode" style="align-items:flex-start">{!! \App\Support\Barcode::svg($case->case_code, 48) !!}<span>{{ $case->case_code }}</span></div>
        <div class="qr">{!! \App\Support\Barcode::qr(route('cases.show', $case), 96) !!}<small>امسح لفتح الحالة</small></div>
    </div>
    <div class="grid">
        <div><span>المريض</span>{{ $case->patient->name }}</div>
        <div><span>رقم الملف</span><b class="ltr num">{{ $case->patient->file_number }}</b></div>
        <div><span>الموبايل</span><span class="ltr num" style="color:inherit;font-size:inherit">{{ $case->patient->phone ?? '—' }}</span></div>
        <div><span>كود الحالة</span><b class="ltr num">{{ $case->case_code }}</b></div>
        <div><span>نوع الفحص</span>{{ $case->examType?->name ?? '—' }}</div>
        <div><span>تاريخ الفحص</span><span class="ltr num" style="color:inherit;font-size:inherit">{{ $case->exam_date->format('d/m/Y') }}</span></div>
        <div><span>الطبيب</span>{{ $case->doctor?->display_name ?? '—' }}</div>
        <div><span>الفرع</span>{{ $case->branch->name }}</div>
        <div><span>سجّلها</span>{{ $case->creator?->name ?? '—' }}</div>
    </div>
    @if($case->notes_for_doctor)<h3 style="margin-top:20px">ملاحظات للطبيب</h3><p style="white-space:pre-line">{{ $case->notes_for_doctor }}</p>@endif
    @if($case->medical_notes)<h3>التقرير والملاحظات الطبية</h3><p style="white-space:pre-line">{{ $case->medical_notes }}</p>@endif
    @if($case->files->isNotEmpty())
        <h3 style="margin-top:20px">الملفات</h3>
        <table><tr><th>الملف</th><th>النوع</th><th>الحجم</th></tr>
            @foreach($case->files as $f)<tr><td class="ltr">{{ $f->original_name }}</td><td>{{ $f->type->shortLabel() }}</td><td class="num ltr">{{ $f->humanSize() }}</td></tr>@endforeach
        </table>
        @php($images = $case->files->filter(fn ($f) => $f->isImage()))
        @if($images->isNotEmpty())
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px">
                @foreach($images as $f)<img src="{{ route('files.show', [$f, 'view']) }}" alt="{{ $f->original_name }}" style="width:100%;border:1px solid var(--line);border-radius:6px;background:#000">@endforeach
            </div>
        @endif
    @endif
</x-print-sheet>
