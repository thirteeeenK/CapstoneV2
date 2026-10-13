<?php

use App\Http\Controllers\Admin\PlannerInventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->prefix('admin/planner-inventory')->name('admin.planner-inventory.')->group(function () {
    Route::get('/', [PlannerInventoryController::class, 'index'])->name('index');
    Route::patch('/{type}/{id}', [PlannerInventoryController::class, 'update'])->whereIn('type', ['activity', 'hotel'])->whereNumber('id')->name('update');
});
