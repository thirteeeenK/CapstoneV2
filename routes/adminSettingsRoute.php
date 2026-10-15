<?php

use App\Http\Controllers\Admin\AdminSettingsController;
use App\Services\SystemSettingsService;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:admin', 'admin.2fa', 'no.cache', 'throttle:admin'])->group(function () {
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
    Route::patch('/settings/{section}', [AdminSettingsController::class, 'update'])
        ->whereIn('section', SystemSettingsService::SECTIONS)->name('admin.settings.update');
});
