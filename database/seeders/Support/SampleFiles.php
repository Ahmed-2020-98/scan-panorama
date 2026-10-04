<?php

namespace Database\Seeders\Support;

use ZipArchive;

/**
 * Generates small, obviously fake sample files for the demo data
 * (x-ray-like PNG, referral sheet PNG, report PDF, DICOM zip).
 */
class SampleFiles
{
    public static function radiograph(string $label, bool $threeD = false): string
    {
        [$width, $height] = $threeD ? [900, 900] : [1400, 640];
        $image = imagecreatetruecolor($width, $height);

        imagefill($image, 0, 0, self::color($image, 12, 14, 18));

        // Soft glow bands to look like a radiograph
        for ($i = 0; $i < 60; $i++) {
            $shade = 20 + $i * 2;
            $color = self::color($image, $shade, $shade + 4, $shade + 8, 110);
            imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.55), (int) ($width * (0.95 - $i * 0.01)), (int) ($height * (0.8 - $i * 0.008)), $color);
        }

        // "Teeth"
        $tooth = self::color($image, 225, 228, 232, 30);
        $count = $threeD ? 8 : 16;
        $span = $threeD ? $width * 0.6 : $width * 0.7;
        $start = ($width - $span) / 2;

        for ($row = 0; $row < 2; $row++) {
            for ($i = 0; $i < $count; $i++) {
                $x = (int) ($start + $i * ($span / $count));
                $y = (int) ($height * ($row === 0 ? 0.36 : 0.56));
                imagefilledrectangle($image, $x + 4, $y, $x + (int) ($span / $count) - 6, $y + (int) ($height * 0.14), $tooth);
            }
        }

        $text = self::color($image, 150, 220, 220);
        imagestring($image, 5, 24, 20, strtoupper($label), $text);
        imagestring($image, 3, 24, 44, 'SAMPLE IMAGE - NOT FOR DIAGNOSIS', $text);

        return self::png($image);
    }

    public static function referral(string $exam, string $caseCode): string
    {
        $image = imagecreatetruecolor(800, 1060);
        imagefill($image, 0, 0, self::color($image, 255, 255, 255));

        $ink = self::color($image, 30, 41, 59);
        $muted = self::color($image, 148, 163, 184);
        $accent = self::color($image, 13, 148, 136);

        imagefilledrectangle($image, 0, 0, 800, 90, $accent);
        imagestring($image, 5, 40, 36, 'REFERRAL SHEET - SAMPLE', self::color($image, 255, 255, 255));

        $y = 140;
        foreach (['Case code: '.$caseCode, 'Requested exam: '.$exam, 'Region: ________________', 'Clinical notes:'] as $line) {
            imagestring($image, 5, 40, $y, $line, $ink);
            $y += 50;
        }

        for ($line = 0; $line < 12; $line++) {
            imageline($image, 40, $y + $line * 40, 760, $y + $line * 40, $muted);
        }

        imagestring($image, 4, 40, 980, 'Doctor signature: ______________', $ink);

        return self::png($image);
    }

    /**
     * @param  list<string>  $lines
     */
    public static function reportPdf(array $lines): string
    {
        $stream = "BT\n/F1 20 Tf\n60 780 Td\n(".self::pdfEscape(array_shift($lines)).") Tj\n/F1 12 Tf\n";

        foreach ($lines as $line) {
            $stream .= "0 -26 Td\n(".self::pdfEscape($line).") Tj\n";
        }

        $stream .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    public static function dicomZip(string $caseCode): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dcm');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        for ($i = 1; $i <= 3; $i++) {
            $zip->addFromString(sprintf('DICOM/%s/IM%06d.dcm', $caseCode, $i), str_repeat("\0", 128).'DICM'.random_bytes(2048));
        }

        $zip->addFromString('README.txt', "Sample DICOM package for {$caseCode}. Demo data only.");
        $zip->close();

        $contents = (string) file_get_contents($path);
        unlink($path);

        return $contents;
    }

    /**
     * @param  int<0, 255>  $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     * @param  int<0, 127>  $alpha
     */
    private static function color(\GdImage $image, int $red, int $green, int $blue, int $alpha = 0): int
    {
        $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);

        if ($color === false) {
            throw new \RuntimeException('Unable to allocate an image color.');
        }

        return $color;
    }

    private static function png(\GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    private static function pdfEscape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
