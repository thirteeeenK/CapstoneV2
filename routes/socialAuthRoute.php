<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Social Authentication (Google OAuth via Laravel Socialite)
|--------------------------------------------------------------------------
|
| Both legs are guest-only: an already-signed-in traveler is bounced to the
| dashboard by the guest middleware rather than swapping identities midway.
| The callback path must match the authorized redirect URI registered on the
| Google Cloud OAuth client (see GOOGLE_REDIRECT_URI in .env.example).
|
*/

Route::middleware(['guest', 'throttle:users'])->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});
