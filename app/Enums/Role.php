<?php

namespace App\Enums;

enum Role: string
{
    case Manager = 'manager';

    public const Admin = self::Manager;
    case Reception = 'reception';
    case Doctor = 'doctor';
    case Technician = 'technician';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير النظام',
            self::Reception => 'استقبال',
            self::Doctor => 'طبيب',
            self::Technician => 'فني',
            self::Marketing => 'مسؤول الدعاية',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'purple',
            self::Reception => 'sky',
            self::Doctor => 'teal',
            self::Technician => 'amber',
            self::Marketing => 'pink',
        };
    }

    /**
     * What a user of this role can do unless the admin overrides it.
     *
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => [],
            self::Reception => ['view_cases', 'create_case', 'edit_case', 'upload_files', 'share_case', 'view_financials'],
            self::Marketing => ['view_cases', 'manage_visits', 'manage_doctors', 'send_campaigns'],
            self::Doctor, self::Technician => ['view_cases', 'upload_files'],
        };
    }

    /**
     * Roles that operate the center (not doctors).
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Admin, self::Reception, self::Marketing];
    }
}
