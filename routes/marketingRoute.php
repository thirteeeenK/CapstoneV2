<?php

use App\Http\Controllers\MarketingPreferenceController;
use Illuminate\Support\Facades\Route;

// The unsubscribe link works without signing in; its signature is the proof.
// The POST is also the one-click (RFC 8058) endpoint, exempt from CSRF in bootstrap/app.php.
Route::middleware(['signed', 'throttle:30,1'])->prefix('email/unsubscribe')->name('marketing.')->group(function () {
    Route::get('/{user}', [MarketingPreferenceController::class, 'confirm'])->name('unsubscribe.confirm');
    Route::post('/{user}', [MarketingPreferenceController::class, 'unsubscribe'])->name('unsubscribe');
});

Route::middleware(['auth', 'throttle:users'])->patch('/profile/email-preferences', [MarketingPreferenceController::class, 'update'])
    ->name('profile.email-preferences');
