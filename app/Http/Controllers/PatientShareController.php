<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\MedicalCase;
use App\Support\ActivityLogger;
use App\Support\FileResponder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

/**
 * Public, token-based access for the patient (link sent via WhatsApp). Shares the
 * doctor link's expiry and revocation, but never shows doctor-facing notes and
 * never counts as the doctor opening the case.
 */
class PatientShareController extends Controller
{
    private const PRIVATE_HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'private, no-store',
    ];

    public function show(Request $request, string $token): Response
    {
        $case = $this->find($token);
        if (! $case || ! $case->isShareActive()) {
            return response()
                ->view('shared.unavailable', ['expired' => $case !== null, 'forPatient' => true], $case ? 410 : 404)
                ->withHeaders(self::PRIVATE_HEADERS);
        }

        if (! $request->user()?->isStaff()) {
            ActivityLogger::log('patient_share.opened', $case);
        }

        $case->load(['doctor.user', 'branch', 'examType', 'files' => fn ($q) => $q->where('is_shared', true)->where('storage_status', 'ready')]);

        return response()
            ->view('shared.patient', ['case' => $case])
            ->withHeaders(self::PRIVATE_HEADERS);
    }

    public function file(string $token, CaseFile $file, string $mode): FileResponse
    {
        $case = $this->find($token);
        abort_unless($case && $case->isShareActive() && $file->medical_case_id === $case->id && $file->is_shared && $file->storage_status === 'ready', 404);

        return FileResponder::respond($file, $mode);
    }

    private function find(string $token): ?MedicalCase
    {
        $case = MedicalCase::withoutGlobalScope('access')->where('patient_share_token', $token)->first();
        $case?->load(['patient' => fn ($q) => $q->withoutGlobalScope('access')]);

        return $case;
    }
}
