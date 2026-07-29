<?php
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\AdminAuth\AdminAuth;
use Illuminate\Support\Facades\Route;


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