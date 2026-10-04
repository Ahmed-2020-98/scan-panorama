<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function minor(string|int $value): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['price' => 'أدخل مبلغًا موجبًا بمنزلتين عشريتين كحد أقصى.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(?int $minor): string
    {
        return $minor === null ? '' : sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
    }

    public static function display(?int $minor): string
    {
        return $minor === null ? 'غير مسعّرة' : number_format($minor / 100, 2);
    }
}
