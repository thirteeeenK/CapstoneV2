<?php

use App\Http\Controllers\Admin\AdminBookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('admin.bookings.index');
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show'])->name('admin.bookings.show');
        Route::post('/bookings/{id}/approve', [AdminBookingController::class, 'approve'])->name('admin.bookings.approve');
        Route::post('/bookings/{id}/reject', [AdminBookingController::class, 'reject'])->name('admin.bookings.reject');
        Route::post('/bookings/{id}/cancel', [AdminBookingController::class, 'cancel'])->name('admin.bookings.cancel');
        Route::post('/bookings/{id}/cancel-request/approve', [AdminBookingController::class, 'approveCancellation'])->name('admin.bookings.cancel-request.approve');
        Route::post('/bookings/{id}/cancel-request/deny', [AdminBookingController::class, 'denyCancellation'])->name('admin.bookings.cancel-request.deny');
        Route::post('/bookings/{id}/mark-paid', [AdminBookingController::class, 'markPaid'])->name('admin.bookings.mark-paid');
        Route::post('/bookings/{id}/mark-completed', [AdminBookingController::class, 'markCompleted'])->name('admin.bookings.mark-completed');
        Route::post('/bookings/{id}/mark-refunded', [AdminBookingController::class, 'markRefunded'])->name('admin.bookings.mark-refunded');
    });
});
