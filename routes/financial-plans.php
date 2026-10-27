<?php

use App\Http\Controllers\FinancialPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('financial-plans')->name('financial-plans.')->middleware(['auth', 'staff.role', 'app.module:financial_plans'])->group(function () {
    Route::get('/', [FinancialPlanController::class, 'index'])->name('index');
    Route::post('/departments', [FinancialPlanController::class, 'storeDepartment'])->name('departments.store');
    Route::put('/departments/{department}', [FinancialPlanController::class, 'updateDepartment'])->name('departments.update');
    Route::post('/departments/{department}/plans', [FinancialPlanController::class, 'storePlan'])->name('store');
    Route::get('/{plan}', [FinancialPlanController::class, 'show'])->name('show');
    Route::get('/{plan}/calculation', [FinancialPlanController::class, 'calculation'])->name('calculation');
    Route::put('/{plan}/period', [FinancialPlanController::class, 'updatePeriod'])->name('period.update');
    Route::post('/{plan}/lines', [FinancialPlanController::class, 'storeLine'])->name('lines.store');
    Route::put('/{plan}/lines/{line}', [FinancialPlanController::class, 'updateLine'])->name('lines.update');
    Route::delete('/{plan}/lines/{line}', [FinancialPlanController::class, 'destroyLine'])->name('lines.destroy');
});
