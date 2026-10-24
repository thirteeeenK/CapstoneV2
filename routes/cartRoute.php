<?php

use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Only the routes that create or clear basket lines are throttled; the drawer
// polls data and quantity steppers fire per click, so those stay unlimited.
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::get('/data', [CartController::class, 'data'])->name('data');
    Route::post('/add', [CartController::class, 'store'])->middleware('throttle:cart')->name('add');
    Route::post('/add-itinerary', [CartController::class, 'addItinerary'])->middleware('throttle:cart')->name('add-itinerary');
    Route::patch('/update/{id}', [CartController::class, 'update'])->name('update');
    Route::post('/toggle/{id}', [CartController::class, 'toggleSelect'])->name('toggle');
    Route::post('/toggle-all', [CartController::class, 'toggleAll'])->name('toggle-all');
    Route::post('/toggle-group/{groupId}', [CartController::class, 'toggleGroup'])->name('toggle-group');
    Route::delete('/remove/{id}', [CartController::class, 'destroy'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->middleware('throttle:cart')->name('clear');
});
