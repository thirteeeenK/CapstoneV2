<?php

use App\Http\Controllers\User\ChatbotTripPlanController;
use App\Http\Controllers\User\TripPlannerController;
use Illuminate\Support\Facades\Route;

// Chatbot drafts are saveable before sign-in and claimed onto the account at
// login, the same way the trip basket keeps a guest's cart. Session-keyed
// 'cart' throttle rather than 'dss' for the same reason.
Route::middleware(['throttle:cart'])->prefix('trip-planner')->name('trip-planner.')->group(function () {
    Route::post('/chatbot-plans', [ChatbotTripPlanController::class, 'store'])->name('chatbot-store');
    Route::get('/chatbot-plans', [ChatbotTripPlanController::class, 'index'])->name('chatbot-status');
});

Route::middleware(['auth', 'throttle:dss'])->prefix('trip-planner')->name('trip-planner.')->group(function () {
    Route::get('/', [TripPlannerController::class, 'index'])->name('index');
    Route::get('/catalog/{destination}', [TripPlannerController::class, 'catalog'])->name('catalog');
    Route::get('/weather/{destination}', [TripPlannerController::class, 'weather'])->name('weather');
    Route::post('/plans', [TripPlannerController::class, 'store'])->name('store');
    Route::get('/plans', [TripPlannerController::class, 'plans'])->name('plans');
    Route::patch('/email-preferences', [TripPlannerController::class, 'preferences'])->name('preferences');
    Route::get('/plans/{plan}', [TripPlannerController::class, 'show'])->name('show');
    Route::patch('/plans/{plan}', [TripPlannerController::class, 'update'])->name('update');
    Route::delete('/plans/{plan}', [TripPlannerController::class, 'destroy'])->name('destroy');
    Route::post('/plans/{plan}/items', [TripPlannerController::class, 'add'])->name('add');
    Route::delete('/plans/{plan}/items/{item}', [TripPlannerController::class, 'remove'])->name('remove');
    Route::post('/plans/{plan}/generate', [TripPlannerController::class, 'generate'])->middleware('throttle:6,1')->name('generate');
    Route::post('/plans/{plan}/itineraries/{itinerary}/cart', [TripPlannerController::class, 'transfer'])->name('transfer');
    Route::get('/plans/{plan}/itineraries/{itinerary}/document', [TripPlannerController::class, 'document'])->name('document');
    Route::post('/plans/{plan}/itineraries/{itinerary}/email', [TripPlannerController::class, 'email'])->middleware('throttle:1,10')->name('email');
});
