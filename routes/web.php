<?php

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

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::get('/client/dashboard', function () {
    return view('client.dashboard');
})->middleware(['auth'])->name('client.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', fn () => redirect()->route('settings.users.index'))->name('index');
        Route::resource('users', Settings\UserController::class)->names('users');
        Route::get('company', [Settings\CompanySettingsController::class, 'index'])->name('company');
        Route::post('company', [Settings\CompanySettingsController::class, 'update'])->name('company.update');
    });
});

// Session check endpoint (used by session-expired modal JS)
Route::get('/session-check', function () {
    return response()->json(['authenticated' => auth()->check()]);
})->name('session.check');

require __DIR__.'/auth.php';
