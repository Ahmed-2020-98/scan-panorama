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
 * Public, token-based access to a single case (link sent to the doctor via WhatsApp).
 */
class SharedCaseController extends Controller
{
    private const PRIVATE_HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'private, no-store',
    ];

    public function show(Request $request, string $token): Response
    {
        $case = MedicalCase::withoutGlobalScope('access')->where('share_token', $token)->first();

        if ($case) {
            $case->load(['patient' => fn ($q) => $q->withoutGlobalScope('access')]);
        }
        if (! $case || ! $case->isShareActive()) {
            return response()
                ->view('shared.unavailable', ['expired' => $case !== null], $case ? 410 : 404)
                ->withHeaders(self::PRIVATE_HEADERS);
        }

        if (! $request->user()?->isStaff()) {
            $case->recordOpen();
            ActivityLogger::log('share.opened', $case);
        }

        $case->load(['patient' => fn ($q) => $q->withoutGlobalScope('access'), 'doctor.user', 'branch', 'examType', 'files' => fn ($q) => $q->where('is_shared', true)->where('storage_status', 'ready')]);

        return response()
            ->view('shared.show', ['case' => $case])
            ->withHeaders(self::PRIVATE_HEADERS);
    }

    public function file(string $token, CaseFile $file, string $mode): FileResponse
    {
        $case = MedicalCase::withoutGlobalScope('access')->where('share_token', $token)->first();

        if ($case) {
            $case->load(['patient' => fn ($q) => $q->withoutGlobalScope('access')]);
        }
        abort_unless($case && $case->isShareActive() && $file->medical_case_id === $case->id && $file->is_shared && $file->storage_status === 'ready', 404);

        return FileResponder::respond($file, $mode);
    }
}
