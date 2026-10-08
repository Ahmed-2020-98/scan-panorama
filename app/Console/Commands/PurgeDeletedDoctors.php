<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\DoctorVisit;
use App\Models\MedicalCase;
use App\Support\ActivityLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

#[Signature('doctors:purge')]
#[Description('Permanently remove doctors deleted longer ago than the restore window, when nothing references them')]
class PurgeDeletedDoctors extends Command
{
    public function handle(): int
    {
        $purged = self::purge();
        $this->info("Purged {$purged} doctor(s).");

        return self::SUCCESS;
    }

    public static function purge(): int
    {
        $cutoff = now()->subDays((int) config('radiology.doctor_restore_days', 30));
        $purged = 0;

        Doctor::onlyTrashed()->with('user')->where('deleted_at', '<=', $cutoff)->each(function (Doctor $doctor) use (&$purged) {
            $referenced = MedicalCase::withoutGlobalScopes()->where('doctor_id', $doctor->id)->exists()
                || DoctorVisit::withTrashed()->where('doctor_id', $doctor->id)->exists();
            if ($referenced) {
                return; // Archived for case history; no longer restorable.
            }
            try {
                DB::transaction(function () use ($doctor) {
                    ActivityLogger::log('doctor.purged', $doctor, ['name' => $doctor->display_name]);
                    $user = $doctor->user;
                    $doctor->forceDelete();
                    $user->delete();
                });
                $purged++;
            } catch (QueryException) {
                // Still referenced somewhere (e.g. activity rows); keep it archived.
            }
        });

        return $purged;
    }
}
