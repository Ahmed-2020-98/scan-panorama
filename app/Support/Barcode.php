<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Code 128 (set B) barcode rendered as inline SVG, so case printouts carry a
 * scannable code without a package. A USB scanner types the code back into search;
 * the QR beside it opens a link straight from a phone camera.
 */
class Barcode
{
    /** Bar/space widths for symbols 0..105; 106 is the stop symbol. */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /** Module widths (bar, space, bar, …) including start, checksum and stop. */
    public static function modules(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        $codes = [self::START_B];
        foreach (str_split($text) as $char) {
            $codes[] = ord($char) - 32;
        }
        $checksum = self::START_B;
        foreach (array_slice($codes, 1) as $i => $code) {
            $checksum += $code * ($i + 1);
        }
        $codes[] = $checksum % 103;
        $codes[] = self::STOP;

        return implode('', array_map(fn (int $code) => self::PATTERNS[$code], $codes));
    }

    public static function svg(string $text, int $height = 50, float $module = 1.5): string
    {
        $x = 10 * $module; // quiet zone
        $rects = '';
        foreach (str_split(self::modules($text)) as $i => $width) {
            $w = (int) $width * $module;
            if ($i % 2 === 0) {
                $rects .= sprintf('<rect x="%s" y="0" width="%s" height="%d"/>', $x, $w, $height);
            }
            $x += $w;
        }
        $total = $x + 10 * $module;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$d" width="%1$s" height="%2$d" role="img" aria-label="%3$s" shape-rendering="crispEdges" fill="#000">%4$s</svg>',
            $total, $height, e($text), $rects,
        );
    }

    /** QR code as inline SVG (no XML prolog) for a URL a phone camera can open. */
    public static function qr(string $url, int $size = 110): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($url);

        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }
}
