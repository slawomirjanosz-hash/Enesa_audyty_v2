<?php

use App\Http\Controllers\CompanyReliabilityController;
use App\Http\Controllers\CompanyReliabilityFileController;
use Illuminate\Support\Facades\Route;

Route::prefix('companies/{company}/reliability')->name('companies.reliability.')->middleware(['auth', 'staff.role'])->group(function () {
    Route::get('/', [CompanyReliabilityController::class, 'show'])->name('show');
    Route::post('/financial-preview', [CompanyReliabilityController::class, 'financialPreview'])->middleware('throttle:120,1,reliability-preview:')->name('financial-preview');
    Route::post('/files', [CompanyReliabilityFileController::class, 'store'])->middleware('throttle:10,1,reliability-upload:')->name('files.store');
    Route::get('/files/{file}', [CompanyReliabilityFileController::class, 'download'])->name('files.download');
    Route::post('/files/{file}/import', [CompanyReliabilityFileController::class, 'import'])->middleware('throttle:10,1,reliability-import:')->name('files.import');
    Route::delete('/files/{file}', [CompanyReliabilityFileController::class, 'destroy'])->name('files.destroy');
    Route::post('/lookup', [CompanyReliabilityController::class, 'lookup'])->middleware('throttle:5,1,reliability-lookup:')->name('lookup');
    Route::post('/', [CompanyReliabilityController::class, 'store'])->middleware('throttle:5,1,reliability-report:')->name('store');
    Route::get('/{report}/pdf', [CompanyReliabilityController::class, 'download'])->name('download');
    Route::delete('/{report}', [CompanyReliabilityController::class, 'destroy'])->name('destroy');
});
