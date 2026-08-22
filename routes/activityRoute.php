<?php

use App\Http\Controllers\ActivityShowController;
use App\Http\Controllers\Admin\ActivityController;
use Illuminate\Support\Facades\Route;

// Public Activities & Tours Catalog Listing
Route::get('/activities', [ActivityShowController::class, 'index'])->name('activities.index');
Route::get('/activities/{id}/preview', [ActivityShowController::class, 'preview'])->name('activities.preview');

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        // Activity & Tour Management
        Route::get('/activities', [ActivityController::class, 'index'])->name('admin.activities.index');
        Route::get('/activities/create', [ActivityController::class, 'create'])->name('admin.activities.create');
        Route::post('/activities', [ActivityController::class, 'store'])->name('admin.activities.store');
        Route::get('/activities/{id}/edit', [ActivityController::class, 'edit'])->name('admin.activities.edit');
        Route::put('/activities/{id}', [ActivityController::class, 'update'])->name('admin.activities.update');
        Route::delete('/activities/{id}', [ActivityController::class, 'destroy'])->name('admin.activities.destroy');
    });
});
