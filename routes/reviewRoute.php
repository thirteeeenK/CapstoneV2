<?php

use App\Http\Controllers\User\ReviewController;
use Illuminate\Support\Facades\Route;

// Public discovery hub for verified traveler feedback
Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');

// Authenticated review submission
Route::middleware(['auth', 'throttle:users'])->group(function () {
    Route::get('/reviews/eligible', [ReviewController::class, 'eligible'])->name('reviews.eligible');
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});