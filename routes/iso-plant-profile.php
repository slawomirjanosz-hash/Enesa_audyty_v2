<?php

use App\Http\Controllers\IsoPlantProfileController;
use Illuminate\Support\Facades\Route;

foreach ([false, true] as $client) {
    Route::prefix($client ? 'client/audits/{audit}/plant-profiles' : 'audits/{audit}/plant-profiles')
        ->name($client ? 'client.audits.plant-profile.' : 'audits.plant-profile.')
        ->middleware($client ? ['auth', 'client.role', 'app.module:client_zone', 'app.module:audits'] : ['auth', 'staff.role', 'app.module:audits', 'app.permission:audits.view'])
        ->group(function () {
            Route::get('/', [IsoPlantProfileController::class, 'index'])->name('index');
            Route::post('/', [IsoPlantProfileController::class, 'create'])->name('create');
            Route::get('/{profile}', [IsoPlantProfileController::class, 'show'])->name('show');
            Route::post('/{profile}', [IsoPlantProfileController::class, 'update'])->name('update');
            Route::post('/{profile}/pdf', [IsoPlantProfileController::class, 'pdf'])->middleware('throttle:10,1')->name('pdf');
        });
}
