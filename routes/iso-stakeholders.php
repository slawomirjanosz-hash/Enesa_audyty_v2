<?php

use App\Http\Controllers\IsoStakeholderReviewController;
use Illuminate\Support\Facades\Route;

foreach ([false, true] as $client) {
    Route::prefix($client ? 'client/audits/{audit}/stakeholders/{profile}' : 'audits/{audit}/stakeholders/{profile}')
        ->name($client ? 'client.audits.stakeholders.' : 'audits.stakeholders.')
        ->middleware($client ? ['auth', 'client.role', 'app.module:client_zone', 'app.module:audits'] : ['auth', 'staff.role', 'app.module:audits', 'app.permission:audits.view'])
        ->group(function () {
            Route::get('/', [IsoStakeholderReviewController::class, 'show'])->name('show');
            Route::post('/', [IsoStakeholderReviewController::class, 'update'])->name('update');
            Route::post('/pdf', [IsoStakeholderReviewController::class, 'pdf'])->middleware('throttle:10,1')->name('pdf');
        });
}
