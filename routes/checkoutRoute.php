<?php

use App\Http\Controllers\BookingConfirmationController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:users'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::get('/booking/{code}/success', [BookingConfirmationController::class, 'show'])->name('booking.success');
});
