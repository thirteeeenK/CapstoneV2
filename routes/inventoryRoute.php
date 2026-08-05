<?php

use App\Http\Controllers\Admin\InventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('admin.inventory.index');
        Route::post('/inventory/toggle-visibility', [InventoryController::class, 'toggleVisibility'])->name('admin.inventory.toggle-visibility');
    });
});
