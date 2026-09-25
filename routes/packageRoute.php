<?php

use App\Http\Controllers\PackageShowController;
use Illuminate\Support\Facades\Route;

// Public Tour Packages Catalog Listing
Route::get('/packages', [PackageShowController::class, 'index'])->name('packages.index');

// Package preview payload for the shared preview modal (catalog + chat widget)
Route::get('/packages/{id}/preview', [PackageShowController::class, 'preview'])->name('packages.preview');
