<?php

use App\Http\Controllers\Admin\LegalDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache'])->group(function () {
        Route::get('/legal', [LegalDocumentController::class, 'index'])->name('admin.legal.index');
        Route::get('/legal/{key}/edit', [LegalDocumentController::class, 'edit'])->name('admin.legal.edit');
        Route::put('/legal/{key}', [LegalDocumentController::class, 'update'])->name('admin.legal.update');
    });
});