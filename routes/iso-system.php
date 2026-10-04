<?php

use App\Http\Controllers\IsoSystemReviewController;
use Illuminate\Support\Facades\Route;

foreach ([false, true] as $client) {
    Route::prefix($client ? 'client/audits/{audit}/system/{profile}/{section}' : 'audits/{audit}/system/{profile}/{section}')
        ->name($client ? 'client.audits.system.' : 'audits.system.')
        ->where(['section' => '4-3|4-4'])
        ->middleware($client ? ['auth', 'client.role', 'app.module:client_zone', 'app.module:audits'] : ['auth', 'staff.role', 'app.module:audits', 'app.permission:audits.view'])
        ->group(function () {
            Route::get('/', [IsoSystemReviewController::class, 'show'])->name('show');
            Route::post('/', [IsoSystemReviewController::class, 'update'])->name('update');
            Route::post('/pdf', [IsoSystemReviewController::class, 'pdf'])->middleware('throttle:10,1')->name('pdf');
        });
}
