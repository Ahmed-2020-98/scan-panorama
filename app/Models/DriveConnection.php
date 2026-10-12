<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * The center's Google account. Exactly one row is "active" (files are read from
 * and written to it); a "target" row exists only while files are being copied to
 * a new account, and the previous account is kept as "retired" after the switch.
 *
 * @property int $id
 * @property string $refresh_token
 * @property string|null $root_folder_id
 * @property string|null $shared_drive_id
 * @property string|null $account_id
 * @property string|null $account_email
 * @property string $role
 */
#[Fillable(['refresh_token', 'root_folder_id', 'shared_drive_id', 'account_id', 'account_email', 'role'])]
#[Hidden(['refresh_token'])]
class DriveConnection extends Model
{
    protected $attributes = ['role' => 'active'];

    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted'];
    }

    public static function active(): ?self
    {
        return static::where('role', 'active')->first();
    }

    public static function activeOrFail(): self
    {
        return static::where('role', 'active')->firstOrFail();
    }

    public static function target(): ?self
    {
        return static::where('role', 'target')->first();
    }

    public static function connected(): bool
    {
        return static::where('role', 'active')->exists();
    }
}
