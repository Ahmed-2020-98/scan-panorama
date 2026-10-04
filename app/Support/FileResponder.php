<?php

namespace App\Support;

use App\Models\CaseFile;
use App\Services\Drive\DriveCaseStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a private case file to the browser and records the access.
 */
class FileResponder
{
    public static function respond(CaseFile $file, string $mode): Response
    {
        abort_unless($file->storage_status === 'ready' && ! $file->trashed(), 404);
        if ($file->disk === 'drive') {
            ActivityLogger::log($mode === 'view' ? 'file.viewed' : 'file.downloaded', $file, ['name' => $file->original_name, 'type' => $file->type->value]);

            return app(DriveCaseStorage::class)->respond($file, $mode);
        }
        $disk = Storage::disk($file->disk ?: config('radiology.disk'));

        abort_unless($disk->exists($file->path), 404, 'الملف غير موجود على الخادم.');

        $inline = $mode === 'view' && $file->isViewable();

        ActivityLogger::log($inline ? 'file.viewed' : 'file.downloaded', $file, [
            'name' => $file->original_name,
            'type' => $file->type->value,
        ]);

        $headers = [
            'Content-Type' => $file->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if ($inline && $file->isVideo() && config('filesystems.disks.'.$file->disk.'.driver') === 'local') {
            return response()->file($disk->path($file->path), $headers);
        }

        return $inline
            ? $disk->response($file->path, $file->original_name, $headers, 'inline')
            : $disk->download($file->path, $file->original_name, $headers);
    }
}
