<?php

namespace App\Jobs;

use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Models\User;
use App\Services\Drive\DriveCaseStorage;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Gate;

class UploadCaseFileToDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 1800;

    public function __construct(public int $fileId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('drive-file-'.$this->fileId))->releaseAfter(30)->expireAfter(1900)];
    }

    public function handle(DriveCaseStorage $storage): void
    {
        $file = CaseFile::find($this->fileId);
        if (! $file || $file->storage_status === 'ready') {
            return;
        }
        $actor = User::findOrFail($file->uploaded_by);
        Gate::forUser($actor)->authorize('uploadFiles', MedicalCase::withoutGlobalScope('access')->findOrFail($file->medical_case_id));
        $file->update(['storage_status' => 'uploading', 'storage_error' => null]);
        try {
            $storage->store($file);
        } catch (\Throwable $error) {
            $file->update(['storage_status' => 'pending', 'storage_error' => 'تعذر النقل إلى Drive. تتم إعادة المحاولة تلقائيًا.']);
            throw $error;
        }
        ActivityLogger::log('file.drive_ready', $file, ['name' => $file->original_name, 'uploaded_by' => $file->uploaded_by]);
    }

    public function failed(?\Throwable $error): void
    {
        CaseFile::whereKey($this->fileId)->update(['storage_status' => 'failed', 'storage_error' => 'تعذر النقل إلى Drive. تحقق من الاتصال ثم أعد المحاولة خلال 7 أيام.']);
    }
}
