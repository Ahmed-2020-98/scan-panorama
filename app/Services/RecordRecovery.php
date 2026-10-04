<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordRecovery
{
    public static function delete(User $actor, Model $record): void
    {
        abort_unless($actor->isAdmin() && in_array($record::class, [Patient::class, MedicalCase::class, CaseFile::class], true), 403);
        DB::transaction(function () use ($record) {
            $batch = (string) Str::uuid();
            if ($record instanceof Patient) {
                $record->cases()->each(function ($case) use ($batch) {
                    self::markCase($case, $batch);
                });
            } elseif ($record instanceof MedicalCase) {
                self::markCase($record, $batch);

                return;
            }
            self::mark($record, $batch);
        });
    }

    private static function markCase(MedicalCase $case, string $batch): void
    {
        $case->files()->each(fn ($file) => self::mark($file, $batch));
        $case->revokeShareLink();
        self::mark($case, $batch);
    }

    private static function mark(Model $record, string $batch): void
    {
        $record->forceFill(['deletion_batch_id' => $batch])->saveQuietly();
        ActivityLogger::log(self::kind($record).'.deleted', $record, ['name' => $record instanceof CaseFile ? $record->original_name : ($record instanceof Patient ? $record->name : $record->getAttribute('case_code')), 'batch' => $batch]);
        $record->delete();
    }

    public static function restore(User $actor, Model $record): void
    {
        abort_unless($actor->isAdmin() && in_array($record::class, [Patient::class, MedicalCase::class, CaseFile::class], true), 403);
        if ($record instanceof CaseFile) {
            abort_unless(MedicalCase::withTrashed()->find($record->medical_case_id)?->deleted_at === null, 422, 'استرجع الحالة أولًا.');
        }
        if ($record instanceof MedicalCase) {
            abort_unless(Patient::withTrashed()->find($record->patient_id)?->deleted_at === null, 422, 'استرجع المريض أولًا.');
        }
        DB::transaction(function () use ($actor, $record) {
            $batch = $record->deletion_batch_id;
            $record->restore();
            if ($batch && $record instanceof Patient) {
                $record->cases()->onlyTrashed()->where('deletion_batch_id', $batch)->each(fn ($case) => self::restore($actor, $case));
            } elseif ($batch && $record instanceof MedicalCase) {
                $record->files()->onlyTrashed()->where('deletion_batch_id', $batch)->each(fn ($file) => self::restore($actor, $file));
            }
            ActivityLogger::log(self::kind($record).'.recovered', $record, ['batch' => $batch]);
        });
    }

    private static function kind(Model $record): string
    {
        return match (true) {
            $record instanceof Patient => 'patient', $record instanceof CaseFile => 'file', default => 'case'
        };
    }
}
