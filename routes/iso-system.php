<?php

use App\Http\Controllers\IsoImplementationController;
use App\Http\Controllers\IsoSystemReviewController;
use Illuminate\Support\Facades\Route;

foreach ([false, true] as $client) {
    Route::prefix($client ? 'client/audits/{audit}/iso-excel/3-1/{action}' : 'audits/{audit}/iso-excel/3-1/{action}')
        ->name($client ? 'client.audits.implementation.' : 'audits.implementation.')
        ->where(['action' => 'baseline|business_case|sponsor'])
        ->middleware($client ? ['auth', 'client.role', 'app.module:client_zone', 'app.module:audits'] : ['auth', 'staff.role', 'app.module:audits', 'app.permission:audits.view'])
        ->group(function () {
            Route::get('/', [IsoImplementationController::class, 'excel'])->middleware('throttle:10,1')->name('excel');
            Route::post('/', [IsoImplementationController::class, 'importExcel'])->middleware('throttle:10,1')->name('excel-import');
        });
    Route::prefix($client ? 'client/audits/{audit}/system/{profile}/{section}' : 'audits/{audit}/system/{profile}/{section}')
        ->name($client ? 'client.audits.system.' : 'audits.system.')
        ->where(['section' => '4-3|4-4|5-1|5-2|5-3|6-[1-6]|7-[1-5]|8-[1-3]|9-1-[12]|9-[23]|10-[12]'])
        ->middleware($client ? ['auth', 'client.role', 'app.module:client_zone', 'app.module:audits'] : ['auth', 'staff.role', 'app.module:audits', 'app.permission:audits.view'])
        ->group(function () {
            Route::get('/', [IsoSystemReviewController::class, 'show'])->name('show');
            Route::post('/', [IsoSystemReviewController::class, 'update'])->name('update');
            Route::get('/excel', [IsoSystemReviewController::class, 'excel'])->middleware('throttle:10,1')->name('excel');
            Route::post('/excel', [IsoSystemReviewController::class, 'importExcel'])->middleware('throttle:10,1')->name('excel-import');
            Route::post('/pdf', [IsoSystemReviewController::class, 'pdf'])->middleware('throttle:10,1')->name('pdf');
        });
}
