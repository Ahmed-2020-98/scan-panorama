<?php

namespace App\Http\Middleware;

use App\Models\UploadSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs staff out on their next request once their account's days/hours are over.
 * A file already being uploaded may finish: its chunks are let through and the
 * sign-out waits until no upload is still receiving chunks.
 */
class EnforceWorkHours
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->canWorkNow()) {
            return $next($request);
        }

        if ($request->routeIs('cases.uploads.store') && UploadSession::where('user_id', $user->id)->where('upload_id', (string) $request->input('upload_id'))->exists()) {
            return $next($request);
        }

        $message = 'حسابك مفتوح '.$user->workScheduleLabel().' بس.';

        // Signing out now would cut the files still uploading: block other pages until they finish.
        $uploading = UploadSession::where('user_id', $user->id)->whereNull('case_file_id')->where('updated_at', '>=', now()->subMinutes(2))->exists();
        if ($uploading && ! $request->routeIs('cases.uploads.store')) {
            abort(423, $message.' الملفات اللي بتترفع هتكمل، وبعدها هتخرج.');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->routeIs('cases.uploads.store')) {
            return response()->json(['message' => $message], 423);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
