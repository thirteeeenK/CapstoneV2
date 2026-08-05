<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PackageShowController;

// Public Tour Packages Catalog Listing
Route::get('/packages', [PackageShowController::class, 'index'])->name('packages.index');
