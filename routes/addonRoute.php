<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AddOnController;
use App\Http\Controllers\AddOnShowController;

// Public Transfers & Add-ons Catalog Listing
Route::get('/addons', [AddOnShowController::class, 'index'])->name('addons.index');

Route::prefix('admin/addons')->middleware(['auth:admin', 'throttle:admin'])->name('admin.addons.')->group(function () {
    Route::get('/', [AddOnController::class, 'index'])->name('index');
    Route::get('/create', [AddOnController::class, 'create'])->name('create');
    Route::post('/', [AddOnController::class, 'store'])->name('store');
    Route::get('/{addon}/edit', [AddOnController::class, 'edit'])->name('edit');
    Route::put('/{addon}', [AddOnController::class, 'update'])->name('update');
    Route::delete('/{addon}', [AddOnController::class, 'destroy'])->name('destroy');
});
