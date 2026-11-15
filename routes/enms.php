<?php

use App\Http\Controllers\EnmsLeadershipController;
use Illuminate\Support\Facades\Route;

foreach ([false, true] as $client) {
    Route::prefix($client ? 'client/audits/{audit}/enms/{profile}' : 'audits/{audit}/enms/{profile}')
        ->name($client ? 'client.audits.enms.' : 'audits.enms.')
        ->middleware($client ? ['auth', 'client.role', 'app.module:client_zone', 'app.module:audits'] : ['auth', 'staff.role', 'app.module:audits', 'app.permission:audits.view'])
        ->group(function () {
            Route::get('/', [EnmsLeadershipController::class, 'show'])->name('show');
            Route::post('/', [EnmsLeadershipController::class, 'update'])->name('update');
            Route::post('/draft', [EnmsLeadershipController::class, 'draft'])->middleware('throttle:10,1')->name('draft');
            Route::get('/excel', [EnmsLeadershipController::class, 'excel'])->middleware('throttle:10,1')->name('excel');
            Route::post('/excel', [EnmsLeadershipController::class, 'importExcel'])->middleware('throttle:10,1')->name('excel-import');
            Route::get('/documents/{record}', [EnmsLeadershipController::class, 'document'])->whereUuid('record')->middleware('throttle:20,1')->name('document');
        });
}
