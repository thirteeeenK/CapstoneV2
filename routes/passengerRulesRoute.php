<?php

use App\Http\Controllers\Admin\PassengerRuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        Route::get('/passenger-rules', [PassengerRuleController::class, 'index'])->name('admin.passenger-rules.index');
        Route::post('/passenger-rules/{id}', [PassengerRuleController::class, 'update'])->name('admin.passenger-rules.update');
    });
});
