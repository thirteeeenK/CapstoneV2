<?php

use App\Http\Controllers\Admin\AdminReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('admin.reviews.index');
        Route::get('/reviews/create', [AdminReviewController::class, 'create'])->name('admin.reviews.create');
        Route::post('/reviews', [AdminReviewController::class, 'store'])->name('admin.reviews.store');
        Route::post('/reviews/{id}/toggle-publish', [AdminReviewController::class, 'togglePublish'])->name('admin.reviews.toggle-publish');
        Route::post('/reviews/{id}/toggle-featured', [AdminReviewController::class, 'toggleFeatured'])->name('admin.reviews.toggle-featured');
        Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy'])->name('admin.reviews.destroy');
    });
});
