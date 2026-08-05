<?php
use App\Http\Controllers\AdminAuth\AdminAuth;
use App\Http\Controllers\AdminAuth\AdminNewPasswordController;
use App\Http\Controllers\AdminAuth\AdminPasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['guest:admin', 'throttle:admin'])->group(function () {
        Route::get('/login', [AdminAuth::class, 'create'])->name('admin.login');
        Route::post('/login', [AdminAuth::class, 'store']);

        Route::get('/forgot-password', [AdminPasswordResetLinkController::class, 'create'])
            ->name('admin.password.request');

        Route::post('/forgot-password', [AdminPasswordResetLinkController::class, 'store'])
            ->name('admin.password.email');

        Route::get('/reset-password/{token}', [AdminNewPasswordController::class, 'create'])
            ->name('admin.password.reset');

        Route::post('/reset-password', [AdminNewPasswordController::class, 'store'])
            ->name('admin.password.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('admin.dashboard');

        Route::post('/logout', [AdminAuth::class, 'destroy'])->name('admin.logout');
    });
});