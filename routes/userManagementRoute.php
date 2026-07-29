<?php

use App\Http\Controllers\Admin\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache'])->group(function () {
        // Registered User Management & Chatbot Moderation Routes
        Route::get('/registered-users', [RegisteredUserController::class, 'index'])->name('admin.users.index');
        Route::get('/registered-users/{id}', [RegisteredUserController::class, 'show'])->name('admin.users.show');
        Route::post('/registered-users/{id}/ban', [RegisteredUserController::class, 'ban'])->name('admin.users.ban');
        Route::post('/registered-users/{id}/unban', [RegisteredUserController::class, 'unban'])->name('admin.users.unban');
        Route::post('/registered-users/reports/{reportId}/dismiss', [RegisteredUserController::class, 'dismissReport'])->name('admin.users.dismiss-report');
    });
});
