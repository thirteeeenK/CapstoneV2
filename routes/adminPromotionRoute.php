<?php

use App\Http\Controllers\Admin\AdminPromotionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:admin', 'admin.2fa', 'no.cache', 'throttle:admin'])->group(function () {
    Route::get('/promotions', [AdminPromotionController::class, 'index'])->name('admin.promotions.index');
    Route::get('/promotions/create', [AdminPromotionController::class, 'create'])->name('admin.promotions.create');
    Route::post('/promotions', [AdminPromotionController::class, 'store'])->name('admin.promotions.store');
    Route::get('/promotions/{promotion}/edit', [AdminPromotionController::class, 'edit'])->name('admin.promotions.edit');
    Route::put('/promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('admin.promotions.update');
    Route::post('/promotions/{promotion}/toggle', [AdminPromotionController::class, 'toggle'])->name('admin.promotions.toggle');
});
