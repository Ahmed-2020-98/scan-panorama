<?php

namespace App\Http\Controllers;

use App\Models\DriveConnection;
use App\Services\Drive\DriveMigration;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DriveConnectionController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() && config('drive.client_id') && config('drive.client_secret'), 403);
        $state = Str::random(64);
        $request->session()->put('drive.oauth_state', $state);
        $request->session()->put('drive.oauth_started', now()->timestamp);
        $request->session()->put('drive.oauth_migrate', $request->boolean('migrate') && DriveConnection::connected());

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id' => config('drive.client_id'), 'redirect_uri' => config('drive.redirect_uri') ?: route('drive.callback'), 'response_type' => 'code', 'scope' => 'https://www.googleapis.com/auth/drive.file', 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state]));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $expected = $request->session()->pull('drive.oauth_state');
        $started = $request->session()->pull('drive.oauth_started', 0);
        $migrate = (bool) $request->session()->pull('drive.oauth_migrate', false);
        abort_unless(is_string($expected) && is_string($request->query('state')) && hash_equals($expected, $request->query('state')) && now()->timestamp - $started < 600, 403);
        if ($request->has('error') || ! $request->query('code')) {
            return redirect()->route('drive.settings')->with('drive_error', 'لم يكتمل تفويض Google Drive.');
        }
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', ['code' => $request->query('code'), 'client_id' => config('drive.client_id'), 'client_secret' => config('drive.client_secret'), 'redirect_uri' => config('drive.redirect_uri') ?: route('drive.callback'), 'grant_type' => 'authorization_code']);
        if (! $response->successful() || ! $response->json('refresh_token')) {
            return redirect()->route('drive.settings')->with('drive_error', 'تعذر حفظ اتصال Google Drive. أعد الربط.');
        }
        $identity = Http::withToken($response->json('access_token'))->timeout(30)->get('https://www.googleapis.com/drive/v3/about', ['fields' => 'user(permissionId,emailAddress)']);
        if (! $identity->successful() || ! $identity->json('user.permissionId')) {
            return redirect()->route('drive.settings')->with('drive_error', 'تعذر التحقق من هوية حساب Drive. أعد الربط.');
        }
        $accountId = (string) $identity->json('user.permissionId');
        $email = $identity->json('user.emailAddress');
        $connection = DriveConnection::active();

        if ($migrate && $connection) {
            if ($connection->account_id === $accountId) {
                return redirect()->route('drive.settings')->with('drive_error', 'هذا هو الحساب المربوط حاليًا. سجّل الدخول بالحساب الجديد الذي تريد النقل إليه.');
            }
            DriveMigration::cancel();
            DriveConnection::create(['refresh_token' => $response->json('refresh_token'), 'account_id' => $accountId, 'account_email' => $email, 'role' => 'target']);
            ActivityLogger::log('drive.migration_started', null, ['to' => $email]);
            DriveMigration::runAfterResponse();

            return redirect()->route('drive.settings')->with('drive_success', 'تم ربط الحساب الجديد وبدأ نقل الملفات إليه. اترك هذه الصفحة مفتوحة حتى يكتمل النقل.');
        }
        if ($connection && $connection->account_id !== $accountId) {
            return redirect()->route('drive.settings')->with('drive_error', 'هذا حساب مختلف عن حساب المركز الحالي. لنقل الملفات إليه استخدم «نقل الملفات إلى حساب آخر».');
        }
        if ($connection) {
            $connection->update(['refresh_token' => $response->json('refresh_token'), 'account_email' => $email ?? $connection->account_email]);
        } else {
            DriveConnection::create(['refresh_token' => $response->json('refresh_token'), 'account_id' => $accountId, 'account_email' => $email]);
        }
        ActivityLogger::log('drive.connected');

        return redirect()->route('drive.settings')->with('drive_success', 'تم ربط حساب المركز.');
    }
}
