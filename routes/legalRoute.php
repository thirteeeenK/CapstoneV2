<?php

use App\Http\Controllers\Admin\LegalDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/legal', [LegalDocumentController::class, 'index'])->name('admin.legal.index');
        Route::get('/legal/create', [LegalDocumentController::class, 'create'])->name('admin.legal.create');
        Route::post('/legal', [LegalDocumentController::class, 'store'])->name('admin.legal.store');
        Route::get('/legal/{key}/edit', [LegalDocumentController::class, 'edit'])->name('admin.legal.edit');
        Route::put('/legal/{key}', [LegalDocumentController::class, 'update'])->name('admin.legal.update');
        Route::delete('/legal/{key}', [LegalDocumentController::class, 'destroy'])->name('admin.legal.destroy');
    });
});