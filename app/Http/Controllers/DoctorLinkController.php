<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Doctor;
use App\Models\MedicalCase;
use App\Support\ActivityLogger;
use App\Support\FileResponder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

/**
 * The doctor's private link: every case the doctor would see in the portal,
 * without logging in. Dies with the token, a deleted doctor or a disabled account.
 */
class DoctorLinkController extends Controller
{
    private const PRIVATE_HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'private, no-store',
    ];

    public function index(Request $request, string $token): Response
    {
        $doctor = $this->doctor($token);
        if (! $doctor) {
            return $this->unavailable();
        }

        $search = trim((string) $request->query('q', ''));
        $cases = $doctor->linkCases()
            ->with(['patient' => fn ($q) => $q->withoutGlobalScope('access'), 'branch', 'examType', 'files' => fn ($q) => $q->where('storage_status', 'ready')])
            ->search($search)
            ->orderByDesc('exam_date')->orderByDesc('id')
            ->get();

        if (! $request->user()?->isStaff()) {
            ActivityLogger::log('doctor_link.opened', $doctor);
        }

        return response()->view('doctor-link.index', ['doctor' => $doctor, 'cases' => $cases, 'search' => $search, 'token' => $token])
            ->withHeaders(self::PRIVATE_HEADERS);
    }

    public function show(Request $request, string $token, int $case): Response
    {
        $doctor = $this->doctor($token);
        $medicalCase = $doctor?->linkCases()->whereKey($case)->first();
        if (! $doctor || ! $medicalCase) {
            return $this->unavailable();
        }

        if (! $request->user()?->isStaff()) {
            $medicalCase->recordOpen();
            ActivityLogger::log('doctor_link.case_opened', $medicalCase);
        }
        $medicalCase->load(['patient' => fn ($q) => $q->withoutGlobalScope('access'), 'doctor.user', 'branch', 'examType', 'files' => fn ($q) => $q->where('storage_status', 'ready')]);

        return response()->view('doctor-link.case', ['doctor' => $doctor, 'case' => $medicalCase, 'token' => $token])
            ->withHeaders(self::PRIVATE_HEADERS);
    }

    public function file(Request $request, string $token, CaseFile $file, string $mode): FileResponse
    {
        $doctor = $this->doctor($token);
        $case = $doctor?->linkCases()->whereKey($file->medical_case_id)->first();
        abort_unless($case instanceof MedicalCase && $file->storage_status === 'ready', 404);

        if (! $request->user()?->isStaff()) {
            $case->recordOpen();
        }

        return FileResponder::respond($file, $mode);
    }

    private function doctor(string $token): ?Doctor
    {
        $doctor = Doctor::with('user')->where('link_token', $token)->first();

        return $doctor && $doctor->user->is_active ? $doctor : null;
    }

    private function unavailable(): Response
    {
        return response()->view('shared.unavailable', ['expired' => false, 'forPatient' => true], 404)->withHeaders(self::PRIVATE_HEADERS);
    }
}
