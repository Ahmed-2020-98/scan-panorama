<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $case_file_id
 */
#[Fillable(['user_id', 'medical_case_id', 'upload_id', 'type', 'file_name', 'file_size', 'total_chunks', 'case_file_id', 'status'])]
class UploadSession extends Model {}
