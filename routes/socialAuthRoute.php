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
| The redirect leg is POST-only because it carries the Cloudflare Turnstile
| token minted by the sign-in button. A GET variant would be an unverified way
| into the same flow, so none is offered.
|
*/

Route::middleware(['guest', 'throttle:users'])->group(function () {
    Route::post('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});
