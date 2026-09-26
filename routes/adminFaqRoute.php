<?php

use App\Http\Controllers\Admin\AdminFaqController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/faqs', [AdminFaqController::class, 'index'])->name('admin.faqs.index');
        Route::get('/faqs/create', [AdminFaqController::class, 'create'])->name('admin.faqs.create');
        Route::post('/faqs', [AdminFaqController::class, 'store'])->name('admin.faqs.store');
        Route::get('/faqs/{id}/edit', [AdminFaqController::class, 'edit'])->name('admin.faqs.edit');
        Route::put('/faqs/{id}', [AdminFaqController::class, 'update'])->name('admin.faqs.update');
        Route::post('/faqs/{id}/toggle-visibility', [AdminFaqController::class, 'toggleVisibility'])->name('admin.faqs.toggle-visibility');
        Route::post('/faqs/{id}/toggle-page-visibility', [AdminFaqController::class, 'togglePageVisibility'])->name('admin.faqs.toggle-page-visibility');
        Route::delete('/faqs/{id}', [AdminFaqController::class, 'destroy'])->name('admin.faqs.destroy');
    });
});
