<?php

use App\Http\Controllers\DssController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'throttle:ai'])->group(function () {
    Route::get('/explore', [DssController::class, 'explore'])->name('explore');

    Route::get('/api/dss/weather', [DssController::class, 'weather'])->name('api.dss.weather');
    Route::get('/api/dss/destinations', [DssController::class, 'destinations'])->name('api.dss.destinations');
    Route::get('/api/dss/markers', [DssController::class, 'markers'])->name('api.dss.markers');
    Route::get('/api/dss/nearby', [DssController::class, 'nearby'])->name('api.dss.nearby');
});
