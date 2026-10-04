<?php

namespace App\Support;

/**
 * Picks a chunk size that always fits the server's PHP upload limits, so large
 * DICOM archives upload on any host without touching php.ini.
 */
class UploadLimits
{
    private const SAFETY_MARGIN = 256 * 1024;

    private const MIN_CHUNK = 256 * 1024;

    public static function chunkSize(): int
    {
        $serverLimit = min(
            self::iniBytes('upload_max_filesize'),
            self::iniBytes('post_max_size'),
        );

        $configured = (int) config('radiology.max_chunk_mb', 8) * 1024 * 1024;

        return max(self::MIN_CHUNK, min($configured, $serverLimit - self::SAFETY_MARGIN));
    }

    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '0' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
