<?php

use App\Http\Controllers\ExpenseInvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('expenses')->name('expenses.')->middleware(['auth', 'staff.role', 'app.module:expenses'])->group(function () {
    Route::get('/', [ExpenseInvoiceController::class, 'index'])->name('index');
    Route::get('/create', [ExpenseInvoiceController::class, 'create'])->name('create');
    Route::post('/', [ExpenseInvoiceController::class, 'store'])->name('store');
    Route::post('/scan', [ExpenseInvoiceController::class, 'scan'])->middleware('throttle:10,1')->name('scan');
    Route::get('/{invoice}/edit', [ExpenseInvoiceController::class, 'edit'])->name('edit');
    Route::put('/{invoice}', [ExpenseInvoiceController::class, 'update'])->name('update');
    Route::post('/{invoice}/claim', [ExpenseInvoiceController::class, 'claim'])->name('claim');
    Route::post('/{invoice}/settle', [ExpenseInvoiceController::class, 'settle'])->name('settle');
});
