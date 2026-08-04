<?php

use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\LegalContentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('landing');

Route::get('/terms-and-conditions', [LegalContentController::class, 'show'])->defaults('slug', 'terms')->name('terms');
Route::get('/privacy-policy', [LegalContentController::class, 'show'])->defaults('slug', 'privacy-policy')->name('privacy-policy');
Route::get('/ai-disclosure', [LegalContentController::class, 'show'])->defaults('slug', 'ai-disclosure')->name('ai-disclosure');
Route::get('/legal/{slug}', [LegalContentController::class, 'show'])->name('legal.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/check', [ConnectionController::class, 'checkConnection']);

require __DIR__ . '/auth.php';
require __DIR__ . '/adminAuth.php';
require __DIR__ . '/destinationRoute.php';
require __DIR__ . '/hotelRoute.php';
require __DIR__ . '/RoomRoute.php';
require __DIR__ . '/activityRoute.php';
require __DIR__ . '/userManagementRoute.php';
require __DIR__ . '/inventoryRoute.php';
require __DIR__ . '/legalRoute.php';
require __DIR__ . '/addonRoute.php';
