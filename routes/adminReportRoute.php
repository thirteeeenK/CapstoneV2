<?php

use App\Http\Controllers\Admin\AdminReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
        Route::get('/reports/export.pdf', [AdminReportController::class, 'exportPdf'])->name('admin.reports.export-pdf');
        Route::post('/reports/analyze', [AdminReportController::class, 'analyze'])->name('admin.reports.analyze');
    });
});
