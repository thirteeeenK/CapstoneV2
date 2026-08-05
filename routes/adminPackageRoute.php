<?php

use App\Http\Controllers\Admin\AdminPackageController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/packages', [AdminPackageController::class, 'index'])->name('admin.packages.index');
        Route::get('/packages/create', [AdminPackageController::class, 'create'])->name('admin.packages.create');
        Route::post('/packages', [AdminPackageController::class, 'store'])->name('admin.packages.store');
        Route::get('/packages/{id}/edit', [AdminPackageController::class, 'edit'])->name('admin.packages.edit');
        Route::put('/packages/{id}', [AdminPackageController::class, 'update'])->name('admin.packages.update');
        Route::post('/packages/{id}/toggle-visibility', [AdminPackageController::class, 'toggleVisibility'])->name('admin.packages.toggle-visibility');
        Route::delete('/packages/{id}', [AdminPackageController::class, 'destroy'])->name('admin.packages.destroy');
    });
});
