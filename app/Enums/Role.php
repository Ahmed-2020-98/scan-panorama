<?php

namespace App\Enums;

enum Role: string
{
    case Manager = 'manager';

    public const Admin = self::Manager;
    case Reception = 'reception';
    case Doctor = 'doctor';
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير النظام',
            self::Reception => 'استقبال',
            self::Doctor => 'طبيب',
            self::Technician => 'فني',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'purple',
            self::Reception => 'sky',
            self::Doctor => 'teal',
            self::Technician => 'amber',
        };
    }

    /**
     * Roles that operate the center (not doctors).
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Admin, self::Reception];
    }
}
