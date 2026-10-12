<?php

namespace App\Services\Drive;

use App\Models\CaseFile;
use App\Models\DriveConnection;
use App\Models\MedicalCase;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies every Drive file from the active account to the "target" account,
 * verifying size and MD5, then switches the system to the new account in one
 * transaction. The old account is left untouched as a backup.
 */
class DriveMigration
{
    public function __construct(private DriveClient $client, private DriveFolderResolver $folders) {}

    /** @return Builder<CaseFile> */
    public static function remaining(): Builder
    {
        return CaseFile::withTrashed()->where('disk', 'drive')->where('storage_status', 'ready')->whereNull('migrated_provider_id');
    }

    /** @return array{total: int, copied: int, failed: int} */
    public static function progress(): array
    {
        $total = CaseFile::withTrashed()->where('disk', 'drive')->where('storage_status', 'ready')->count();
        $copied = CaseFile::withTrashed()->where('disk', 'drive')->whereNotNull('migrated_provider_id')->count();

        return ['total' => $total, 'copied' => min($copied, $total), 'failed' => (int) Cache::get('drive-migration-failed', 0)];
    }

    /**
     * Copy files for up to $seconds. Returns true once the switch has happened.
     */
    public function run(int $seconds): bool
    {
        $target = DriveConnection::target();
        $source = DriveConnection::active();
        if (! $target || ! $source) {
            return true;
        }

        $lock = Cache::lock('drive-migration', $seconds + 120);
        if (! $lock->get()) {
            return false;
        }

        try {
            $deadline = microtime(true) + $seconds;
            $from = $this->client->using($source);
            $to = $this->client->using($target);
            $folders = $this->folders->using($target);
            $failed = [];

            while (microtime(true) < $deadline) {
                $file = self::remaining()->whereNotIn('id', $failed)->orderBy('id')->first();
                if (! $file) {
                    break;
                }
                try {
                    $this->copy($file, $from, $to, $folders);
                } catch (\Throwable $error) {
                    report($error);
                    $failed[] = $file->id;
                }
            }
            Cache::put('drive-migration-failed', count($failed), now()->addDay());

            return $failed === [] && $this->switchIfComplete();
        } finally {
            $lock->release();
        }
    }

    private function copy(CaseFile $file, DriveClient $from, DriveClient $to, DriveFolderResolver $folders): void
    {
        $case = MedicalCase::withoutGlobalScope('access')->withTrashed()->with(['patient' => fn ($q) => $q->withoutGlobalScope('access'), 'branch'])->findOrFail($file->medical_case_id);
        $temp = 'drive-migration/'.$file->id;
        $disk = Storage::disk('local');
        $disk->makeDirectory('drive-migration');
        $path = $disk->path($temp);

        try {
            $response = $from->request()->withOptions(['stream' => true])->timeout(1800)
                ->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode((string) $file->provider_id), ['alt' => 'media', 'supportsAllDrives' => 'true'])
                ->throw();
            $body = $response->toPsrResponse()->getBody();
            $out = fopen($path, 'wb');
            if (! $out) {
                throw new \RuntimeException('Cannot write temporary file.');
            }
            try {
                while (! $body->eof()) {
                    fwrite($out, $body->read(1024 * 1024));
                }
            } finally {
                fclose($out);
                $body->close();
            }
            clearstatcache(true, $path);
            if (! is_file($path) || filesize($path) !== $file->size) {
                throw new \RuntimeException('Downloaded file size does not match for case file '.$file->id);
            }
            $newId = $to->newId();
            DriveCaseStorage::upload($to, $newId, $folders->caseFolder($case, $file->type), $file->original_name, $file->mime, $file->size, $path);
            CaseFile::withTrashed()->whereKey($file->id)->update(['migrated_provider_id' => $newId]);
        } finally {
            $disk->delete($temp);
        }
    }

    /** Point every file at the new account once all have been copied. */
    public function switchIfComplete(): bool
    {
        return DB::transaction(function () {
            $target = DriveConnection::target();
            if (! $target || self::remaining()->exists() || CaseFile::where('disk', 'drive')->where('storage_status', 'uploading')->exists()) {
                return false;
            }
            $source = DriveConnection::active();
            CaseFile::withTrashed()->where('disk', 'drive')->whereNotNull('migrated_provider_id')
                ->update(['provider_id' => DB::raw('migrated_provider_id'), 'migrated_provider_id' => null]);
            // Not yet on Drive: their pre-allocated IDs belong to the old account.
            CaseFile::withTrashed()->where('disk', 'drive')->whereIn('storage_status', ['pending', 'failed'])->update(['provider_id' => null]);
            $source?->update(['role' => 'retired']);
            $target->update(['role' => 'active']);
            ActivityLogger::log('drive.migrated', null, ['from' => $source?->account_email, 'to' => $target->account_email]);
            Cache::forget('drive-migration-failed');

            return true;
        });
    }

    public static function cancel(): void
    {
        DB::transaction(function () {
            DriveConnection::where('role', 'target')->delete();
            CaseFile::withTrashed()->whereNotNull('migrated_provider_id')->update(['migrated_provider_id' => null]);
        });
        Cache::forget('drive-migration-failed');
    }
}
