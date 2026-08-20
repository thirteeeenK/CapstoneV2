<?php

use App\Http\Controllers\Admin\AdminOnboardingOptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/onboarding-options', [AdminOnboardingOptionController::class, 'index'])->name('admin.onboarding-options.index');
        Route::get('/onboarding-options/create', [AdminOnboardingOptionController::class, 'create'])->name('admin.onboarding-options.create');
        Route::post('/onboarding-options', [AdminOnboardingOptionController::class, 'store'])->name('admin.onboarding-options.store');
        Route::get('/onboarding-options/{id}/edit', [AdminOnboardingOptionController::class, 'edit'])->name('admin.onboarding-options.edit');
        Route::put('/onboarding-options/{id}', [AdminOnboardingOptionController::class, 'update'])->name('admin.onboarding-options.update');
        Route::post('/onboarding-options/{id}/toggle-active', [AdminOnboardingOptionController::class, 'toggleActive'])->name('admin.onboarding-options.toggle-active');
        Route::delete('/onboarding-options/{id}', [AdminOnboardingOptionController::class, 'destroy'])->name('admin.onboarding-options.destroy');
    });
});
