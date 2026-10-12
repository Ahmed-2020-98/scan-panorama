@props(['title', 'paper' => 'a4'])
@php
    $centerName = \App\Models\Setting::get('center_name') ?: config('radiology.center_name');
    $phone = \App\Models\Setting::get('contact_phone');
    $logoUrl = \App\Models\Setting::logoUrl() ?: asset('images/brand/logo.png');

@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $centerName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand: #0066d6; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: var(--ink); font: 14px/1.6 'Tajawal', system-ui, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { background: #fff; margin: 24px auto; padding: 28px 32px; box-shadow: 0 10px 30px -18px rgb(15 23 42 / .35); border-radius: 10px; }
        .sheet.a4 { width: 210mm; min-height: 297mm; }
        .sheet.receipt { width: 80mm; padding: 14px 12px; font-size: 12px; }
        header.brand { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 3px solid var(--brand); padding-bottom: 12px; margin-bottom: 18px; }
        .receipt header.brand { flex-direction: column; text-align: center; border-bottom-width: 2px; gap: 6px; }
        header.brand img { height: 56px; width: auto; }
        .receipt header.brand img { height: 40px; }
        h1 { font-size: 20px; margin: 0; } .receipt h1 { font-size: 15px; }
        .muted { color: var(--muted); } .ltr { direction: ltr; unicode-bidi: isolate; } .num { font-variant-numeric: tabular-nums; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: start; padding: 7px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { background: #f8fafc; color: var(--muted); font-weight: 500; font-size: 12px; }
        .receipt th, .receipt td { padding: 4px 2px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px 18px; }
        .grid div span { display: block; color: var(--muted); font-size: 12px; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 12px 0 18px; }
        .kpi { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; } .kpi b { display: block; font-size: 18px; }
        .barcode { display: flex; flex-direction: column; align-items: center; gap: 2px; direction: ltr; } .barcode svg { max-width: 100%; height: auto; } .barcode span { font: 600 12px/1 ui-monospace, monospace; letter-spacing: 2px; }
        .total { display: flex; justify-content: space-between; font-weight: 800; font-size: 15px; border-top: 2px dashed var(--ink); padding-top: 8px; margin-top: 8px; }
        footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid var(--line); color: var(--muted); font-size: 11px; display: flex; justify-content: space-between; }
        .toolbar { position: sticky; top: 0; display: flex; justify-content: center; gap: 8px; padding: 10px; background: #0b1730; }
        .toolbar button { font: inherit; padding: 8px 18px; border-radius: 8px; border: 0; cursor: pointer; background: var(--brand); color: #fff; font-weight: 700; }
        .toolbar button.ghost { background: transparent; color: #cbd5e1; border: 1px solid #334155; }
        @media print {
            body { background: #fff; } .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; border-radius: 0; width: auto; min-height: 0; }
            @page { size: {{ $paper === 'receipt' ? '80mm auto' : 'A4' }}; margin: {{ $paper === 'receipt' ? '4mm' : '12mm' }}; }
            tr { break-inside: avoid; }
        }
        @media (max-width: 820px) { .sheet.a4 { width: auto; margin: 12px; } .grid, .kpis { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">طباعة</button><button type="button" class="ghost" onclick="history.length > 1 ? history.back() : window.close()">رجوع</button></div>
    <main class="sheet {{ $paper }}">
        <header class="brand">
            <img src="{{ $logoUrl }}" alt="{{ $centerName }}">
            <div style="text-align: {{ $paper === 'receipt' ? 'center' : 'left' }}">
                <h1>{{ $title }}</h1>
                @if ($phone)<div class="muted ltr">{{ $phone }}</div>@endif
            </div>
        </header>
        {{ $slot }}
        <footer><span>{{ $centerName }}</span><span class="ltr num">{{ now()->format('d/m/Y H:i') }}</span></footer>
    </main>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
