<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\BookingConfirmationController;
use Illuminate\Support\Facades\Route;

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
Route::get('/booking/{code}/success', [BookingConfirmationController::class, 'show'])->name('booking.success');
