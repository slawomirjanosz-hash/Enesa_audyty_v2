<?php

use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('warehouse')->name('warehouse.')->middleware(['auth', 'staff.role', 'app.module:warehouse'])->group(function () {
    Route::get('/', [WarehouseController::class, 'index'])->name('index');
    Route::get('/export', [WarehouseController::class, 'export'])->middleware('throttle:10,1')->name('export');
    Route::get('/lookup', [WarehouseController::class, 'lookup'])->name('lookup');
    Route::get('/items/create', [WarehouseController::class, 'create'])->middleware('app.permission:warehouse.manage')->name('items.create');
    Route::post('/items', [WarehouseController::class, 'store'])->middleware('app.permission:warehouse.manage')->name('items.store');
    Route::get('/items/{item}', [WarehouseController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/edit', [WarehouseController::class, 'edit'])->middleware('app.permission:warehouse.manage')->name('items.edit');
    Route::put('/items/{item}', [WarehouseController::class, 'update'])->middleware('app.permission:warehouse.manage')->name('items.update');
    Route::patch('/items/{item}/archive', [WarehouseController::class, 'archive'])->middleware('app.permission:warehouse.manage')->name('items.archive');
    Route::get('/documents', [WarehouseController::class, 'documents'])->name('documents.index');
    Route::get('/documents/create/{type}', [WarehouseController::class, 'createDocument'])->name('documents.create');
    Route::post('/documents', [WarehouseController::class, 'storeDocument'])->middleware('throttle:30,1')->name('documents.store');
    Route::get('/documents/{document}', [WarehouseController::class, 'showDocument'])->name('documents.show');
});
