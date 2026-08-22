<?php

use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\HotelShowController;
use Illuminate\Support\Facades\Route;

// Public Hotels Listing & Details
Route::get('/hotels', [HotelShowController::class, 'index'])->name('hotels.index');
Route::get('/hotels/{id}', [HotelShowController::class, 'show'])->name('hotels.show');

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        // Hotel Listings & Create Form
        Route::get('/hotels', [HotelController::class, 'showListing'])->name('view-listings');
        Route::get('/hotels/manage', [HotelController::class, 'create'])->name('manage-hotels');
        Route::post('/hotels/manage', [HotelController::class, 'store'])->name('admin.hotels.store');

        // Edit & Update
        Route::get('/hotels/{id}/edit', [HotelController::class, 'edit'])->name('edit-view');
        Route::put('/hotels/{id}', [HotelController::class, 'editInformation'])->name('edit-information');

        // Delete
        Route::delete('/hotels/{id}', [HotelController::class, 'delete'])->name('deleteHotel');
    });
});
