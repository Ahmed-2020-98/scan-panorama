<?php

use App\Http\Controllers\CaseFileController;
use App\Http\Controllers\CaseUploadController;
use App\Http\Controllers\ContactsExportController;
use App\Http\Controllers\DoctorLinkController;
use App\Http\Controllers\DriveConnectionController;
use App\Http\Controllers\PatientShareController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\SharedCaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect(auth()->user()->homeUrl())
        : redirect()->route('login');
})->name('home');

Route::view('privacy', 'privacy')->name('privacy');

Route::middleware('auth')->group(function () {

    // Center staff: admin + reception
    Route::middleware(['role:manager,reception,marketing', 'permission:view_cases'])->group(function () {
        Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');

        Route::redirect('patients', '/cases')->name('patients.index');
        Route::redirect('patients/create', '/cases/create')->name('patients.create');
        Route::livewire('patients/{patient}', 'pages::admin.patients.show')->name('patients.show');
        Route::livewire('patients/{patient}/edit', 'pages::admin.patients.form')->name('patients.edit');

        Route::livewire('cases', 'pages::admin.cases.index')->name('cases.index');
        Route::livewire('cases/create', 'pages::admin.cases.form')->middleware('permission:create_case')->name('cases.create');
        Route::livewire('cases/{case}', 'pages::admin.cases.show')->name('cases.show');
        Route::livewire('cases/{case}/edit', 'pages::admin.cases.form')->name('cases.edit');
        Route::livewire('notifications', 'pages::admin.notifications')->name('notifications.index');
        Route::get('cases/{case}/print', [PrintController::class, 'case'])->name('cases.print');
        Route::get('cases/{case}/receipt', [PrintController::class, 'receipt'])->name('cases.receipt');

    });

    Route::post('cases/{case}/uploads', CaseUploadController::class)->middleware(['permission:upload_files', 'throttle:uploads'])->name('cases.uploads.store');
    Route::livewire('technician', 'pages::technician.index')->middleware('role:technician')->name('technician');
    Route::livewire('accounts', 'pages::admin.accounts')->middleware('permission:view_financials')->name('accounts.index');
    Route::livewire('visits', 'pages::admin.visits')->middleware('permission:manage_visits')->name('visits.index');
    Route::livewire('expenses', 'pages::admin.expenses')->middleware('permission:manage_expenses')->name('expenses.index');
    Route::livewire('campaigns', 'pages::admin.campaigns')->middleware('permission:send_campaigns')->name('campaigns.index');
    Route::livewire('campaigns/{campaign}', 'pages::admin.campaign')->middleware('permission:send_campaigns')->name('campaigns.show');
    Route::get('contacts/{audience}.vcf', [ContactsExportController::class, '__invoke'])->whereIn('audience', ['patients', 'doctors'])->middleware('permission:send_campaigns')->name('contacts.export');
    Route::get('accounts/print', [PrintController::class, 'accounts'])->middleware('permission:view_financials')->name('accounts.print');

    // Management pages: the admin, or staff the admin granted the matching permission.
    Route::middleware('role:manager,reception,marketing')->group(function () {
        Route::middleware('permission:manage_doctors')->group(function () {
            Route::livewire('doctors', 'pages::admin.doctors.index')->name('doctors.index');
            Route::livewire('doctors/create', 'pages::admin.doctors.form')->name('doctors.create');
            Route::livewire('doctors/{doctor}/edit', 'pages::admin.doctors.form')->name('doctors.edit');
        });
        Route::livewire('recycle-bin', 'pages::admin.recycle-bin')->middleware('permission:delete_case')->name('recycle-bin');
        Route::middleware('permission:manage_branches')->group(function () {
            Route::livewire('branches/{branch}', 'pages::admin.branches.show')->name('branches.show');
            Route::livewire('branches', 'pages::admin.branches')->name('branches.index');
        });
        Route::livewire('exam-types', 'pages::admin.exam-types')->middleware('permission:manage_exam_types')->name('exam-types.index');
        Route::livewire('users', 'pages::admin.users')->middleware('permission:manage_users')->name('users.index');
        Route::livewire('center-settings', 'pages::admin.center-settings')->middleware('permission:manage_settings')->name('center-settings');
        Route::livewire('activity', 'pages::admin.activity')->middleware('permission:view_activity')->name('activity.index');
    });

    // The center's Google account stays with the admin only.
    Route::middleware('role:manager')->group(function () {
        Route::get('drive/connect', [DriveConnectionController::class, 'connect'])->name('drive.connect');
        Route::get('drive/callback', [DriveConnectionController::class, 'callback'])->name('drive.callback');
        Route::livewire('drive', 'pages::admin.drive')->name('drive.settings');
    });

    // Doctor portal
    Route::middleware(['role:doctor', 'permission:view_cases'])->prefix('portal')->group(function () {
        Route::livewire('/', 'pages::portal.index')->name('portal');
        Route::livewire('cases/{case}', 'pages::portal.case')->name('portal.cases.show');
    });

    Route::get('files/{file}/{mode}', CaseFileController::class)
        ->whereIn('mode', ['view', 'download'])
        ->name('files.show');
});

// Public share links (sent to the doctor via WhatsApp)
Route::middleware('throttle:shared')->group(function () {
    Route::get('s/{token}', [SharedCaseController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->name('shared.show');

    Route::get('s/{token}/files/{file}/{mode}', [SharedCaseController::class, 'file'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->whereIn('mode', ['view', 'download'])
        ->name('shared.file');

    // Doctor's private link: all of the doctor's cases without logging in
    Route::get('d/{token}', [DoctorLinkController::class, 'index'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->name('doctor-link.index');

    Route::get('d/{token}/cases/{case}', [DoctorLinkController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->whereNumber('case')
        ->name('doctor-link.case');

    Route::get('d/{token}/files/{file}/{mode}', [DoctorLinkController::class, 'file'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->whereIn('mode', ['view', 'download'])
        ->name('doctor-link.file');

    Route::post('d/{token}/booklet', [DoctorLinkController::class, 'requestBooklet'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->middleware('throttle:6,60')
        ->name('doctor-link.booklet');

    // Patient copy of the case link (no doctor-facing notes)
    Route::get('p/{token}', [PatientShareController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->name('patient-share.show');

    Route::get('p/{token}/files/{file}/{mode}', [PatientShareController::class, 'file'])
        ->where('token', '[A-Za-z0-9]{32,64}')
        ->whereIn('mode', ['view', 'download'])
        ->name('patient-share.file');
});

require __DIR__.'/settings.php';
