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
