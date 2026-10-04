<?php

namespace App\Support;

use App\Models\MedicalCase;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * Generates human-friendly sequential codes:
 *  - cases:    2026-00042 (sequence restarts every year)
 *  - patients: P-00042
 */
class CodeGenerator
{
    public static function caseCode(): string
    {
        $prefix = now()->format('Y').'-';

        return $prefix.self::pad(self::nextSequence('cases-'.$prefix, $prefix, fn () => MedicalCase::withoutGlobalScope('access')->withTrashed()->where('case_code', 'like', $prefix.'%')->pluck('case_code')));
    }

    public static function patientFileNumber(): string
    {
        $prefix = 'P-';

        return $prefix.self::pad(self::nextSequence('patients', $prefix, fn () => Patient::withoutGlobalScope('access')->withTrashed()->where('file_number', 'like', $prefix.'%')->pluck('file_number')));
    }

    /** @param \Closure(): iterable<mixed> $existing */
    private static function nextSequence(string $name, string $prefix, \Closure $existing): int
    {
        return DB::transaction(function () use ($name, $prefix, $existing) {
            DB::table('code_sequences')->insertOrIgnore(['name' => $name, 'value' => 0]);
            $row = DB::table('code_sequences')->where('name', $name)->lockForUpdate()->firstOrFail();
            $next = max((int) $row->value, self::maxSequence($existing(), $prefix)) + 1;
            DB::table('code_sequences')->where('name', $name)->update(['value' => $next]);

            return $next;
        }, 5);
    }

    /**
     * Highest numeric suffix among codes (manually edited, non-numeric codes are ignored).
     *
     * @param  iterable<mixed>  $codes
     */
    private static function maxSequence(iterable $codes, string $prefix): int
    {
        $max = 0;
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)$/';

        foreach ($codes as $code) {
            if (is_string($code) && preg_match($pattern, $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    private static function pad(int $number): string
    {
        return str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
