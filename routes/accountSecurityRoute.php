<?php

use App\Http\Controllers\Auth\GoogleAccountConfirmationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web', 'throttle:users'])->name('account-security.')->prefix('account-security')->group(function () {
    Route::post('google/confirm', [GoogleAccountConfirmationController::class, 'start'])->middleware('throttle:account-security')->name('start');
    Route::get('google/callback', [GoogleAccountConfirmationController::class, 'callback'])->name('callback');
    Route::get('confirm', [GoogleAccountConfirmationController::class, 'show'])->name('show');
    Route::post('confirm', [GoogleAccountConfirmationController::class, 'commit'])->middleware('throttle:account-security')->name('commit');
    Route::post('cancel', [GoogleAccountConfirmationController::class, 'cancel'])->name('cancel');
});
