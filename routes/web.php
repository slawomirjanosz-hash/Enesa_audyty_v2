<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('/client/dashboard', function () {
    return view('client.dashboard');
})->middleware(['auth'])->name('client.dashboard');

Route::middleware('auth')->group(function () {
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

require __DIR__.'/auth.php';
