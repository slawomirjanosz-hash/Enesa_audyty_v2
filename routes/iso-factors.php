<?php

use App\Http\Controllers\IsoFactorReviewController;
use Illuminate\Support\Facades\Route;

foreach ([false, true] as $client) {
    Route::prefix($client ? 'client/audits/{audit}/factors/{profile}' : 'audits/{audit}/factors/{profile}')
        ->name($client ? 'client.audits.factors.' : 'audits.factors.')
        ->middleware($client ? ['auth', 'client.role', 'app.module:client_zone', 'app.module:audits'] : ['auth', 'staff.role', 'app.module:audits', 'app.permission:audits.view'])
        ->group(function () {
            Route::get('/', [IsoFactorReviewController::class, 'show'])->name('show');
            Route::post('/', [IsoFactorReviewController::class, 'update'])->name('update');
            Route::post('/pdf', [IsoFactorReviewController::class, 'pdf'])->middleware('throttle:10,1')->name('pdf');
        });
}
