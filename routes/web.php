<?php

use App\Http\Controllers\CaseFileController;
use App\Http\Controllers\CaseUploadController;
use App\Http\Controllers\DriveConnectionController;
use App\Http\Controllers\SharedCaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect(auth()->user()->homeUrl())
        : redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {

    // Center staff: admin + reception
    Route::middleware(['role:manager,reception', 'permission:view_cases'])->group(function () {
        Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');

        Route::livewire('patients', 'pages::admin.patients.index')->name('patients.index');
        Route::redirect('patients/create', '/cases/create')->name('patients.create');
        Route::livewire('patients/{patient}', 'pages::admin.patients.show')->name('patients.show');
        Route::livewire('patients/{patient}/edit', 'pages::admin.patients.form')->name('patients.edit');

        Route::livewire('cases', 'pages::admin.cases.index')->name('cases.index');
        Route::livewire('cases/create', 'pages::admin.cases.form')->middleware('permission:create_case')->name('cases.create');
        Route::livewire('cases/{case}', 'pages::admin.cases.show')->name('cases.show');
        Route::livewire('cases/{case}/edit', 'pages::admin.cases.form')->name('cases.edit');

    });

    Route::post('cases/{case}/uploads', CaseUploadController::class)->middleware(['permission:upload_files', 'throttle:uploads'])->name('cases.uploads.store');
    Route::livewire('technician', 'pages::technician.index')->middleware('role:technician')->name('technician');
    Route::livewire('accounts', 'pages::admin.accounts')->middleware('permission:view_financials')->name('accounts.index');
    Route::livewire('visits', 'pages::admin.visits')->middleware('permission:manage_visits')->name('visits.index');

    // Admin only
    Route::middleware('role:manager')->group(function () {
        Route::livewire('doctors', 'pages::admin.doctors.index')->name('doctors.index');
        Route::livewire('doctors/create', 'pages::admin.doctors.form')->name('doctors.create');
        Route::livewire('doctors/{doctor}/edit', 'pages::admin.doctors.form')->name('doctors.edit');

        Route::livewire('recycle-bin', 'pages::admin.recycle-bin')->name('recycle-bin');
        Route::get('drive/connect', [DriveConnectionController::class, 'connect'])->name('drive.connect');
        Route::get('drive/callback', [DriveConnectionController::class, 'callback'])->name('drive.callback');
        Route::livewire('drive', 'pages::admin.drive')->name('drive.settings');
        Route::livewire('branches/{branch}', 'pages::admin.branches.show')->name('branches.show');
        Route::livewire('branches', 'pages::admin.branches')->name('branches.index');
        Route::livewire('exam-types', 'pages::admin.exam-types')->name('exam-types.index');
        Route::livewire('users', 'pages::admin.users')->name('users.index');
        Route::livewire('center-settings', 'pages::admin.center-settings')->name('center-settings');
        Route::livewire('activity', 'pages::admin.activity')->name('activity.index');
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
});

require __DIR__.'/settings.php';
