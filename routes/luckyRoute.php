<?php

use App\Http\Controllers\LuckyItineraryController;
use Illuminate\Support\Facades\Route;

Route::prefix('lucky')->name('lucky.')->middleware(['auth'])->group(function () {
    Route::get('/', [LuckyItineraryController::class, 'index'])->name('index');
    Route::post('/generate', [LuckyItineraryController::class, 'generate'])->name('generate');
    Route::post('/accept', [LuckyItineraryController::class, 'accept'])->name('accept');
});
