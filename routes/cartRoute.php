<?php

use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Route::prefix('cart')->name('cart.')->middleware('throttle:cart')->group(function () {

Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::get('/data', [CartController::class, 'data'])->name('data');
    Route::post('/add', [CartController::class, 'store'])->name('add');
    Route::patch('/update/{id}', [CartController::class, 'update'])->name('update');
    Route::post('/toggle/{id}', [CartController::class, 'toggleSelect'])->name('toggle');
    Route::post('/toggle-group/{groupId}', [CartController::class, 'toggleGroup'])->name('toggle-group');
    Route::delete('/remove/{id}', [CartController::class, 'destroy'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
});
