<?php

use App\Http\Controllers\BookingConfirmationController;
use App\Http\Controllers\BookingInstallmentController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\User\MyBookingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:users'])->group(function () {
    Route::get('/bookings', [MyBookingsController::class, 'index'])->name('booking.index');

    Route::prefix('bookings')->name('booking.')->group(function () {
        Route::get('/{code}', [BookingConfirmationController::class, 'show'])->name('show');
        Route::get('/{code}/itinerary/{version}', [BookingConfirmationController::class, 'itinerary'])->whereNumber('version')->name('itinerary');
        Route::post('/{code}/installments', [BookingInstallmentController::class, 'store'])->name('installment.store');
        Route::get('/{code}/payments/{uuid}', [BookingInstallmentController::class, 'show'])->whereUuid('uuid')->name('installment.show');
        Route::post('/{code}/payments/{uuid}/confirm', [BookingInstallmentController::class, 'confirm'])->whereUuid('uuid')->name('installment.confirm');
        Route::post('/{code}/payments/{uuid}/cancel', [BookingInstallmentController::class, 'cancel'])->whereUuid('uuid')->name('installment.cancel');
        Route::post('/{code}/payments/{uuid}/resume', [BookingInstallmentController::class, 'resume'])->whereUuid('uuid')->name('installment.resume');
        Route::get('/{code}/pay', [BookingPaymentController::class, 'show'])->name('pay');
        Route::post('/{code}/pay', [BookingPaymentController::class, 'pay'])->name('pay.process');
        Route::get('/{code}/pay/return', [BookingPaymentController::class, 'return'])->name('pay.return');
        Route::get('/{code}/pay/simulator', [BookingPaymentController::class, 'simulatorShow'])->name('pay.simulator');
        Route::post('/{code}/pay/simulator/confirm', [BookingPaymentController::class, 'simulatorConfirm'])->name('pay.simulator.confirm');
        Route::post('/{code}/pay/qrph', [BookingPaymentController::class, 'qrphInit'])->name('pay.qrph');
        Route::get('/{code}/pay/qrph', [BookingPaymentController::class, 'qrphShow'])->name('pay.qrph.show');
        Route::get('/{code}/pay/qrph/return', [BookingPaymentController::class, 'qrphReturn'])->name('pay.qrph.return');
        Route::post('/{code}/pay/qrph/confirm', [BookingPaymentController::class, 'qrphConfirm'])->name('pay.qrph.confirm');
        Route::post('/{code}/pay/paymongo', [BookingPaymentController::class, 'paymongoInit'])->name('pay.paymongo');
        Route::get('/{code}/pay/paymongo', [BookingPaymentController::class, 'paymongoShow'])->name('pay.paymongo.show');
        Route::get('/{code}/pay/paymongo/return', [BookingPaymentController::class, 'paymongoReturn'])->name('pay.paymongo.return');
        Route::post('/{code}/pay/paymongo/confirm', [BookingPaymentController::class, 'paymongoConfirm'])->name('pay.paymongo.confirm');
        Route::post('/{code}/pay/gotyme', [BookingPaymentController::class, 'goTymeInit'])->name('pay.gotyme');
        Route::get('/{code}/pay/gotyme', [BookingPaymentController::class, 'goTymeShow'])->name('pay.gotyme.show');
        Route::post('/{code}/pay/gotyme/submit', [BookingPaymentController::class, 'goTymeSubmit'])->name('pay.gotyme.submit');
        Route::post('/{code}/cancel', [BookingPaymentController::class, 'cancel'])->name('cancel');
        Route::post('/{code}/cancel/withdraw', [BookingPaymentController::class, 'withdrawCancellation'])->name('cancel.withdraw');
        Route::post('/{code}/rebook', [BookingPaymentController::class, 'rebook'])->name('rebook');
    });
});
