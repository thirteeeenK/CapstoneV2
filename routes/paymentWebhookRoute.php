<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/payment/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->name('payment.webhook')
    ->middleware('throttle:webhook')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
