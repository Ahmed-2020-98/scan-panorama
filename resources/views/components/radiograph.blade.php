@props([
    'label' => 'PANORAMIC · 2D',
    'meta' => '70 kVp · 10 mA · 14.1 s',
    'scan' => true,
])

{{--
    Stylised panoramic radiograph. Teeth are laid out along the "smile" curve of a
    panoramic image and fade in one by one (see .radiograph .tooth in app.css).
--}}
@php
    $uid = 'rg'.substr(md5(uniqid('', true)), 0, 6);
    $centerX = 300;
    $occlusal = 134;
    $curve = 0.00056;

    // From the midline outwards: central, lateral, canine, premolars, molars
    $widths = [19, 18, 21, 22, 22, 30, 30, 27];
    $crowns = [21, 19, 23, 19, 19, 18, 18, 17];
    $roots = [33, 30, 44, 35, 35, 31, 30, 27];

    $toothPath = function (float $w, float $hc, float $hr): string {
        $h = $w / 2;
        $apex = $hc + $hr;
        $f = fn (float $n) => round($n, 1);

        $root = $w >= 26
            ? "L {$f(-$h * 0.62)},{$f(-$apex)} Q {$f(-$h * 0.38)},{$f(-$apex - 3)} {$f(-$h * 0.16)},{$f(-$hc - $hr * 0.5)} Q 0,{$f(-$hc - $hr * 0.4)} {$f($h * 0.16)},{$f(-$hc - $hr * 0.5)} Q {$f($h * 0.38)},{$f(-$apex - 3)} {$f($h * 0.62)},{$f(-$apex)}"
            : "L {$f(-$h * 0.34)},{$f(-$apex * 0.97)} Q 0,{$f(-$apex - 4)} {$f($h * 0.34)},{$f(-$apex * 0.97)}";

        return "M {$f(-$h)},-2 C {$f(-$h)},{$f(-$hc * 0.75)} {$f(-$h * 0.96)},{$f(-$hc)} {$f(-$h * 0.72)},{$f(-$hc)} {$root} L {$f($h * 0.72)},{$f(-$hc)} C {$f($h * 0.96)},{$f(-$hc)} {$f($h)},{$f(-$hc * 0.75)} {$f($h)},-2 Q 0,3 {$f(-$h)},-2 Z";
    };

    $pulpPath = function (float $w, float $hc, float $hr): string {
        $h = $w / 2;
        $f = fn (float $n) => round($n, 1);

        return "M {$f(-$h * 0.22)},{$f(-$hc * 0.45)} Q 0,{$f(-$hc * 0.7)} {$f($h * 0.22)},{$f(-$hc * 0.45)} L {$f($h * 0.07)},{$f(-$hc - $hr * 0.78)} L {$f(-$h * 0.07)},{$f(-$hc - $hr * 0.78)} Z";
    };

    $teeth = [];

    foreach (['upper', 'lower'] as $arch) {
        foreach ([-1, 1] as $side) {
            $x = 1.5;

            foreach ($widths as $n => $w) {
                $offset = $side * ($x + $w / 2);
                $x += $w + 3;

                $scale = $arch === 'lower' ? 0.92 : 1;
                $teeth[] = [
                    'x' => round($centerX + $offset, 1),
                    'y' => round($occlusal + ($arch === 'lower' ? 5 : -1) - $curve * $offset * $offset, 1),
                    'angle' => round(rad2deg(atan(-2 * $curve * $offset)), 2),
                    'flip' => $arch === 'lower',
                    'shape' => $toothPath($w, $crowns[$n] * $scale, $roots[$n] * $scale),
                    'pulp' => $pulpPath($w, $crowns[$n] * $scale, $roots[$n] * $scale),
                    'order' => $n + ($arch === 'lower' ? 3 : 0),
                ];
            }
        }
    }
@endphp

<div {{ $attributes->class(['radiograph overflow-hidden', 'relative' => ! str_contains((string) $attributes->get('class'), 'absolute')]) }} dir="ltr" aria-hidden="true">
    <svg viewBox="0 0 600 260" class="block h-auto w-full" style="direction: ltr">
        <defs>
            <radialGradient id="{{ $uid }}-glow" cx="50%" cy="54%" r="55%">
                <stop offset="0" style="stop-color: var(--color-phosphor)" stop-opacity="0.16" />
                <stop offset="1" style="stop-color: var(--color-phosphor)" stop-opacity="0" />
            </radialGradient>
            <linearGradient id="{{ $uid }}-enamel" x1="0" y1="1" x2="0" y2="0">
                <stop offset="0" stop-color="#f4fffd" stop-opacity="0.95" />
                <stop offset="0.3" stop-color="#d9f5f1" stop-opacity="0.72" />
                <stop offset="1" stop-color="#a9dcd6" stop-opacity="0.18" />
            </linearGradient>
        </defs>

        <ellipse cx="300" cy="140" rx="300" ry="130" fill="url(#{{ $uid }}-glow)" />

        {{-- Anatomy: sinuses, palate, mandible and rami --}}
        <g fill="none" stroke="#dff7f3" stroke-linecap="round">
            <ellipse cx="178" cy="52" rx="72" ry="25" fill="#dff7f3" fill-opacity="0.035" stroke-opacity="0.09" />
            <ellipse cx="422" cy="52" rx="72" ry="25" fill="#dff7f3" fill-opacity="0.035" stroke-opacity="0.09" />
            <path d="M 262,20 C 280,44 320,44 338,20" stroke-opacity="0.1" />
            <path d="M 86,78 C 200,58 400,58 514,78" stroke-opacity="0.12" />
            <path d="M 34,86 C 40,214 168,244 300,244 C 432,244 560,214 566,86" stroke-opacity="0.16" stroke-width="1.4" />
            <path d="M 34,86 L 24,14 M 566,86 L 576,14" stroke-opacity="0.1" />
            <path d="M 60,196 C 150,226 450,226 540,196" stroke-opacity="0.06" stroke-dasharray="2 5" />
        </g>

        {{-- Teeth --}}
        <g>
            @foreach ($teeth as $tooth)
                <g transform="translate({{ $tooth['x'] }} {{ $tooth['y'] }}) rotate({{ $tooth['angle'] }}){{ $tooth['flip'] ? ' scale(1 -1)' : '' }}">
                    <g class="tooth" style="--i: {{ $tooth['order'] }}">
                        <path d="{{ $tooth['shape'] }}" fill="url(#{{ $uid }}-enamel)" stroke="#e9fffb" stroke-opacity="0.35" stroke-width="0.6" />
                        <path d="{{ $tooth['pulp'] }}" fill="#081619" fill-opacity="0.5" />
                    </g>
                </g>
            @endforeach
        </g>

        {{-- DICOM-style overlays --}}
        <g class="overlay-text" font-family="DM Mono, ui-monospace, monospace" fill="#bfeee7" fill-opacity="0.7" font-size="9">
            <rect x="14" y="12" width="22" height="24" rx="3" fill="none" style="stroke: var(--color-marker)" stroke-width="1.2" />
            <text x="25" y="29.5" text-anchor="middle" font-size="15" font-weight="500" style="fill: var(--color-marker)" fill-opacity="1">R</text>

            <text x="586" y="22" text-anchor="end" letter-spacing="1.2">{{ $label }}</text>
            @if ($meta)
                <text x="586" y="35" text-anchor="end" fill-opacity="0.45">{{ $meta }}</text>
            @endif

            <g stroke="#bfeee7" stroke-opacity="0.55">
                <line x1="16" y1="248" x2="116" y2="248" />
                @for ($tick = 0; $tick <= 10; $tick++)
                    <line x1="{{ 16 + $tick * 10 }}" y1="248" x2="{{ 16 + $tick * 10 }}" y2="{{ $tick % 5 === 0 ? 240 : 244 }}" />
                @endfor
            </g>
            <text x="124" y="251">10 mm</text>

            <text x="586" y="251" text-anchor="end" fill-opacity="0.45">W 4000 · L 1500</text>
        </g>
    </svg>

    @if ($scan)
        <div class="scanline"></div>
    @endif
</div>
