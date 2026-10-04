<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\CaseFile;
use App\Models\MedicalCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(string $action, ?Model $subject = null, array $meta = []): ActivityLog
    {
        $request = request();

        return ActivityLog::create([
            'user_id' => auth()->id() ?? ($action === 'file.drive_ready' && $subject instanceof CaseFile ? $subject->uploaded_by : null),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'medical_case_id' => self::caseIdFor($subject),
            'meta' => $meta ?: null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    private static function caseIdFor(?Model $subject): ?int
    {
        return match (true) {
            $subject instanceof MedicalCase => $subject->id,
            $subject instanceof CaseFile => $subject->medical_case_id,
            default => null,
        };
    }
}
