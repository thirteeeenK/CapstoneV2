<?php

use App\Http\Controllers\Admin\AdminAuditController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/audit', [AdminAuditController::class, 'index'])->name('admin.audit.index');
    });
});
