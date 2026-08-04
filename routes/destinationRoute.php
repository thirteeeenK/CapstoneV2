<?php
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\AdminAuth\AdminAuth;
use App\Http\Controllers\DestinationShowController;
use Illuminate\Support\Facades\Route;

Route::get('/destinations/{id}', [DestinationShowController::class, 'show'])->name('destinations.show');

Route::prefix('admin')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuth::class, 'create'])->name('admin.login');
        Route::post('/login', [AdminAuth::class, 'store']);
    });

    Route::middleware(['auth:admin', 'no.cache'])->group(function () {
        Route::get('/destinations', [DestinationController::class, 'index'])->name('admin.destinations');
        Route::post('/destinations', [DestinationController::class, 'store'])->name('admin.store');
        Route::put('/destinations/{id}', [DestinationController::class, 'update'])->name('admin.update');
        Route::delete('/destinations/{id}', [DestinationController::class, 'destroy'])->name('admin.destroy');
    });
});