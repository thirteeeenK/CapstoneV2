<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\AdminAuth\AdminAuth;
use App\Http\Controllers\AdminAuth\AdminNewPasswordController;
use App\Http\Controllers\AdminAuth\AdminPasswordResetLinkController;
use App\Http\Controllers\AdminAuth\TwoFactorChallengeController;
use App\Http\Controllers\AdminAuth\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['guest:admin', 'throttle:admin'])->group(function () {
        Route::get('/login', [AdminAuth::class, 'create'])->name('admin.login');
        Route::post('/login', [AdminAuth::class, 'store']);

        Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'create'])
            ->name('admin.two-factor.challenge');

        Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'store'])
            ->name('admin.two-factor.challenge.store');

        Route::get('/forgot-password', [AdminPasswordResetLinkController::class, 'create'])
            ->name('admin.password.request');

        Route::post('/forgot-password', [AdminPasswordResetLinkController::class, 'store'])
            ->name('admin.password.email');

        Route::get('/reset-password/{token}', [AdminNewPasswordController::class, 'create'])
            ->name('admin.password.reset');

        Route::post('/reset-password', [AdminNewPasswordController::class, 'store'])
            ->name('admin.password.store');
    });

    Route::middleware(['auth:admin', 'admin.2fa'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

        Route::get('/two-factor/setup', [TwoFactorSetupController::class, 'create'])
            ->name('admin.two-factor.setup');

        Route::post('/two-factor/setup', [TwoFactorSetupController::class, 'store'])
            ->name('admin.two-factor.confirm');

        Route::post('/two-factor/recovery-codes', [TwoFactorSetupController::class, 'regenerateCodes'])
            ->name('admin.two-factor.codes.regenerate');

        Route::delete('/two-factor', [TwoFactorSetupController::class, 'destroy'])
            ->middleware('throttle:admin')
            ->name('admin.two-factor.destroy');

        Route::post('/logout', [AdminAuth::class, 'destroy'])->name('admin.logout');
    });
});
