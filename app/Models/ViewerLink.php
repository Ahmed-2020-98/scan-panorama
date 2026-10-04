<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * DICOM viewer download links shown on the doctor portal.
 *
 * @property int $id
 * @property string $name
 * @property string $platform
 * @property string $url
 * @property int $sort
 */
#[Fillable(['name', 'platform', 'url', 'sort'])]
class ViewerLink extends Model
{
    public const PLATFORMS = ['windows' => 'للكمبيوتر (Windows)', 'mobile' => 'للموبايل'];
}
