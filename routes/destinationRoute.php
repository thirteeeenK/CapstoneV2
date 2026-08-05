<?php
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\DestinationShowController;
use Illuminate\Support\Facades\Route;

Route::get('/destinations', [DestinationShowController::class, 'index'])->name('destinations.index');
Route::get('/destinations/{id}', [DestinationShowController::class, 'show'])->name('destinations.show');

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/destinations', [DestinationController::class, 'index'])->name('admin.destinations');
        Route::post('/destinations', [DestinationController::class, 'store'])->name('admin.store');
        Route::put('/destinations/{id}', [DestinationController::class, 'update'])->name('admin.update');
        Route::delete('/destinations/{id}', [DestinationController::class, 'destroy'])->name('admin.destroy');
    });
});