<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $refresh_token
 * @property string|null $root_folder_id
 * @property string|null $shared_drive_id
 * @property string|null $account_id
 */
#[Fillable(['refresh_token', 'root_folder_id', 'shared_drive_id', 'account_id'])]
#[Hidden(['refresh_token'])]
class DriveConnection extends Model
{
    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted'];
    }
}
