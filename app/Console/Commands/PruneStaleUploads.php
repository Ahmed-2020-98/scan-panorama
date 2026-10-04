<?php

namespace App\Console\Commands;

use App\Models\CaseFile;
use App\Models\UploadSession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('uploads:prune')]
#[Description('Delete chunk folders left behind by interrupted uploads')]
class PruneStaleUploads extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours((int) config('radiology.stale_chunks_hours', 24))->getTimestamp();
        $deleted = 0;

        foreach ($disk->directories('chunks') as $directory) {
            $files = $disk->files($directory);
            $lastModified = collect($files)->map(fn (string $file) => $disk->lastModified($file))->max() ?? 0;

            if ($lastModified < $cutoff) {
                $disk->deleteDirectory($directory);
                $deleted++;
            }
        }

        CaseFile::withTrashed()->where('disk', 'drive')->whereNotNull('staging_path')->where('updated_at', '<', now()->subDays((int) config('drive.staging_days', 7)))->each(function ($file) use ($disk) {
            $disk->delete($file->staging_path);
            $file->update(['staging_path' => null, 'storage_status' => 'failed', 'storage_error' => 'انتهت صلاحية الملف المؤقت. أعد رفع الملف من جهازك.']);
        });
        UploadSession::whereNull('case_file_id')->where('updated_at', '<', now()->subHours(24))->delete();
        $this->info("Deleted {$deleted} stale upload(s).");

        return self::SUCCESS;
    }
}
