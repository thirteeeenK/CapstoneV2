<?php

use App\Http\Controllers\Admin\AdminEvaluationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/evaluation', [AdminEvaluationController::class, 'index'])->name('admin.evaluation.index');
    });
});
