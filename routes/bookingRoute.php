<?php

use App\Http\Controllers\BookingConfirmationController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\User\MyBookingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:users'])->group(function () {
    Route::get('/bookings', [MyBookingsController::class, 'index'])->name('booking.index');

    Route::prefix('bookings')->name('booking.')->group(function () {
        Route::get('/{code}', [BookingConfirmationController::class, 'show'])->name('show');
        Route::get('/{code}/pay', [BookingPaymentController::class, 'show'])->name('pay');
        Route::post('/{code}/pay', [BookingPaymentController::class, 'pay'])->name('pay.process');
        Route::get('/{code}/pay/return', [BookingPaymentController::class, 'return'])->name('pay.return');
        Route::get('/{code}/pay/simulator', [BookingPaymentController::class, 'simulatorShow'])->name('pay.simulator');
        Route::post('/{code}/pay/simulator/confirm', [BookingPaymentController::class, 'simulatorConfirm'])->name('pay.simulator.confirm');
        Route::post('/{code}/pay/qrph', [BookingPaymentController::class, 'qrphInit'])->name('pay.qrph');
        Route::get('/{code}/pay/qrph', [BookingPaymentController::class, 'qrphShow'])->name('pay.qrph.show');
        Route::get('/{code}/pay/qrph/return', [BookingPaymentController::class, 'qrphReturn'])->name('pay.qrph.return');
        Route::post('/{code}/pay/qrph/confirm', [BookingPaymentController::class, 'qrphConfirm'])->name('pay.qrph.confirm');
        Route::post('/{code}/cancel', [BookingPaymentController::class, 'cancel'])->name('cancel');
        Route::post('/{code}/cancel/withdraw', [BookingPaymentController::class, 'withdrawCancellation'])->name('cancel.withdraw');
        Route::post('/{code}/rebook', [BookingPaymentController::class, 'rebook'])->name('rebook');
    });
});
