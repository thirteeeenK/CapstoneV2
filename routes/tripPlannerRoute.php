<?php

use App\Http\Controllers\User\TripPlannerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:dss'])->prefix('trip-planner')->name('trip-planner.')->group(function () {
    Route::get('/', [TripPlannerController::class, 'index'])->name('index');
    Route::get('/catalog/{destination}', [TripPlannerController::class, 'catalog'])->name('catalog');
    Route::post('/plans', [TripPlannerController::class, 'store'])->name('store');
    Route::patch('/email-preferences', [TripPlannerController::class, 'preferences'])->name('preferences');
    Route::get('/plans/{plan}', [TripPlannerController::class, 'show'])->name('show');
    Route::delete('/plans/{plan}', [TripPlannerController::class, 'destroy'])->name('destroy');
    Route::post('/plans/{plan}/items', [TripPlannerController::class, 'add'])->name('add');
    Route::delete('/plans/{plan}/items/{item}', [TripPlannerController::class, 'remove'])->name('remove');
    Route::post('/plans/{plan}/generate', [TripPlannerController::class, 'generate'])->middleware('throttle:6,1')->name('generate');
    Route::post('/plans/{plan}/itineraries/{itinerary}/cart', [TripPlannerController::class, 'transfer'])->name('transfer');
    Route::get('/plans/{plan}/itineraries/{itinerary}/document', [TripPlannerController::class, 'document'])->name('document');
    Route::post('/plans/{plan}/itineraries/{itinerary}/email', [TripPlannerController::class, 'email'])->middleware('throttle:1,10')->name('email');
});
