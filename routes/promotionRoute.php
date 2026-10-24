<?php

use App\Http\Controllers\CheckoutPromotionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:users'])->group(function () {
    Route::post('/checkout/promotion', [CheckoutPromotionController::class, 'apply'])->middleware('throttle:10,1')->name('checkout.promotion.apply');
});
