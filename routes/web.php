<?php

use App\Http\Controllers\AuditTypeController;
use App\Http\Controllers\Client\RegistrationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/rejestracja', [RegistrationController::class, 'showForm'])->name('register.client');
Route::post('/rejestracja', [RegistrationController::class, 'register'])->name('register.client.store');

Route::post('/companies/fetch-gus', [CompanyController::class, 'fetchGus'])->name('companies.fetchGus');
Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
    ->middleware('auth')
    ->name('companies.destroy');
Route::post('/companies/{company}/restore', [CompanyController::class, 'restore'])
    ->middleware('auth')
    ->name('companies.restore');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::get('/client/dashboard', function () {
    return view('client.dashboard');
})->middleware(['auth'])->name('client.dashboard');

Route::get('audit-types/versions/{version}/preview', [AuditTypeController::class, 'previewVersion'])->middleware('auth')->name('audit-types.versions.preview');

Route::middleware('auth')->group(function () {
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::post('/companies/{company}/accept', [CompanyController::class, 'accept'])->name('companies.accept');
    Route::post('/companies/{company}/users', [CompanyController::class, 'storeUser'])->name('companies.users.store');
    Route::post('/companies/{company}/assign-existing', [CompanyController::class, 'assignExisting'])->name('companies.users.assignExisting');
    Route::delete('/companies/{company}/users/{user}', [CompanyController::class, 'destroyUser'])->name('companies.users.destroy');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('audit-types', [AuditTypeController::class, 'index'])->name('audit-types.index');
    Route::get('audit-types/{auditType}', [AuditTypeController::class, 'show'])->name('audit-types.show');
    Route::post('audit-types/{auditType}/versions', [AuditTypeController::class, 'storeVersion'])->name('audit-types.versions.store');
    Route::post('audit-types/versions/{version}/set-current', [AuditTypeController::class, 'setAsCurrent'])->name('audit-types.versions.set-current');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', fn () => redirect()->route('settings.users.index'))->name('index');
        Route::resource('users', Settings\UserController::class)->names('users')->except('destroy');
        Route::delete('users/{user}', [Settings\UserController::class, 'destroy'])
            ->middleware('auth')
            ->name('users.destroy')
            ->withTrashed();
        Route::post('users/{user}/restore', [Settings\UserController::class, 'restore'])
            ->name('users.restore')
            ->withTrashed();
        Route::delete('users/{user}/force-destroy', [Settings\UserController::class, 'forceDestroy'])
            ->middleware('auth')
            ->name('users.forceDestroy')
            ->withTrashed();
        Route::post('users/{user}/assign-company', [Settings\UserController::class, 'assignCompany'])
            ->middleware('auth')
            ->name('users.assignCompany');
        Route::post('users/{user}/assign-to-company', [Settings\UserController::class, 'assignToCompany'])
            ->middleware('auth')
            ->name('users.assign-to-company')
            ->withTrashed();
        Route::get('company', [Settings\CompanySettingsController::class, 'index'])->name('company');
        Route::post('company', [Settings\CompanySettingsController::class, 'update'])->name('company.update');
    });
});

// Session check endpoint (used by session-expired modal JS)
Route::get('/session-check', function () {
    return response()->json(['authenticated' => auth()->check()]);
})->name('session.check');

require __DIR__.'/auth.php';
