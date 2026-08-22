<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/payment/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->name('payment.webhook')
    ->middleware('throttle:webhook')
    ->withoutMiddleware(ValidateCsrfToken::class);
