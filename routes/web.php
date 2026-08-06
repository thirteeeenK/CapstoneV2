<?php

use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalContentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/terms-and-conditions', [LegalContentController::class, 'show'])->defaults('slug', 'terms')->name('terms');
Route::get('/privacy-policy', [LegalContentController::class, 'show'])->defaults('slug', 'privacy-policy')->name('privacy-policy');
Route::get('/ai-disclosure', [LegalContentController::class, 'show'])->defaults('slug', 'ai-disclosure')->name('ai-disclosure');
Route::get('/legal/{slug}', [LegalContentController::class, 'show'])->name('legal.show');

use App\Http\Controllers\RecommendationController;

Route::get('/dashboard', [RecommendationController::class, 'index'])
    ->middleware(['auth', 'verified', 'throttle:ai'])
    ->name('dashboard');

use App\Http\Controllers\OnboardingController;

Route::middleware(['auth', 'throttle:users'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::match(['get', 'post'], '/onboarding/reset', [OnboardingController::class, 'reset'])->name('onboarding.reset');
    Route::match(['get', 'post'], '/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

use App\Http\Controllers\NotificationController;

Route::middleware(['auth', 'throttle:users'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

Route::get('/check', [ConnectionController::class, 'checkConnection'])->middleware('throttle:users');

require __DIR__ . '/auth.php';
require __DIR__ . '/adminAuth.php';
require __DIR__ . '/destinationRoute.php';
require __DIR__ . '/hotelRoute.php';
require __DIR__ . '/RoomRoute.php';
require __DIR__ . '/activityRoute.php';
require __DIR__ . '/userManagementRoute.php';
require __DIR__ . '/inventoryRoute.php';
require __DIR__ . '/legalRoute.php';
require __DIR__ . '/addonRoute.php';
require __DIR__ . '/packageRoute.php';
require __DIR__ . '/cartRoute.php';
require __DIR__ . '/checkoutRoute.php';
require __DIR__ . '/passengerRulesRoute.php';
require __DIR__ . '/adminPackageRoute.php';
require __DIR__ . '/bookingRoute.php';
require __DIR__ . '/adminBookingRoute.php';
require __DIR__ . '/paymentWebhookRoute.php';
require __DIR__ . '/reviewRoute.php';
require __DIR__ . '/adminReviewRoute.php';

