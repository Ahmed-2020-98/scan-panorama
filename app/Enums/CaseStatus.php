<?php

namespace App\Enums;

/**
 * Derived (never stored) lifecycle of a medical case.
 */
enum CaseStatus: string
{
    case Incomplete = 'incomplete';
    case Ready = 'ready';
    case Shared = 'shared';
    case Opened = 'opened';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Incomplete => 'بدون ملفات',
            self::Ready => 'جاهزة',
            self::Shared => 'تمت المشاركة',
            self::Opened => 'فتحها الطبيب',
            self::Archived => 'مؤرشفة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Incomplete => 'amber',
            self::Ready => 'sky',
            self::Shared => 'indigo',
            self::Opened => 'green',
            self::Archived => 'zinc',
        };
    }
}
