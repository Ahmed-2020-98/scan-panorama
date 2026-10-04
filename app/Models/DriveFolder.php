<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider_id
 * @property string $logical_key
 */
#[Fillable(['logical_key', 'provider_id'])]
class DriveFolder extends Model {}
