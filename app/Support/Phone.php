<?php

namespace App\Support;

class Phone
{
    /**
     * Normalize a local Egyptian number (01xxxxxxxxx) or international number
     * into the digits-only format wa.me expects (201xxxxxxxxx).
     */
    public static function toInternational(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '20'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Whether the number can receive WhatsApp: a local Egyptian mobile
     * (01xxxxxxxxx) or a full international number.
     */
    public static function isMobile(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        // 01xxxxxxxxx locally, or a country code (optionally 00-prefixed) and 10-15 digits.
        return preg_match('/^01[0125]\d{8}$/', $digits) === 1 || preg_match('/^(00)?[1-9]\d{9,14}$/', $digits) === 1;
    }

    /**
     * Build a WhatsApp click-to-chat link; without a number WhatsApp asks the
     * sender to pick the contact.
     */
    public static function whatsappUrl(?string $phone, string $message = ''): string
    {
        $number = self::toInternational($phone);

        $url = 'https://wa.me/'.($number ?? '');

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }
}
