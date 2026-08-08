<?php

use App\Http\Controllers\DssController;
use Illuminate\Support\Facades\Route;

// DSS pages and JSON endpoints are DB reads / cached weather — they must NOT
// consume the 'ai' limiter (reserved for Gemini calls in chat). The JSON
// endpoints get a generous 'dss' limiter to guard against map-drag hammering.

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/explore', [DssController::class, 'explore'])->name('explore');
});

Route::middleware(['auth', 'verified', 'throttle:dss'])->group(function () {
    Route::get('/api/dss/weather', [DssController::class, 'weather'])->name('api.dss.weather');
    Route::get('/api/dss/destinations', [DssController::class, 'destinations'])->name('api.dss.destinations');
    Route::get('/api/dss/markers', [DssController::class, 'markers'])->name('api.dss.markers');
    Route::get('/api/dss/nearby', [DssController::class, 'nearby'])->name('api.dss.nearby');
});
