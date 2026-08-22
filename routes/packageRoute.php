<?php

use App\Http\Controllers\PackageShowController;
use Illuminate\Support\Facades\Route;

// Public Tour Packages Catalog Listing
Route::get('/packages', [PackageShowController::class, 'index'])->name('packages.index');
