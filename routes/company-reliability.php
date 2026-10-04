<?php

use App\Http\Controllers\CompanyReliabilityController;
use App\Http\Controllers\CompanyReliabilityFileController;
use Illuminate\Support\Facades\Route;

Route::prefix('companies/{company}/reliability')->name('companies.reliability.')->middleware(['auth', 'staff.role'])->group(function () {
    Route::get('/', [CompanyReliabilityController::class, 'show'])->name('show');
    Route::post('/files', [CompanyReliabilityFileController::class, 'store'])->middleware('throttle:10,1')->name('files.store');
    Route::get('/files/{file}', [CompanyReliabilityFileController::class, 'download'])->name('files.download');
    Route::delete('/files/{file}', [CompanyReliabilityFileController::class, 'destroy'])->name('files.destroy');
    Route::post('/lookup', [CompanyReliabilityController::class, 'lookup'])->middleware('throttle:5,1')->name('lookup');
    Route::post('/', [CompanyReliabilityController::class, 'store'])->middleware('throttle:5,1')->name('store');
    Route::get('/{report}/pdf', [CompanyReliabilityController::class, 'download'])->name('download');
    Route::delete('/{report}', [CompanyReliabilityController::class, 'destroy'])->name('destroy');
});
