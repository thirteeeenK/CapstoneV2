<?php

use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\RoomAvailabilityController;
use App\Http\Controllers\RoomShowController;

Route::get('/rooms', [RoomShowController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{id}/availability', [RoomAvailabilityController::class, 'check'])->name('rooms.availability');
Route::get('/rooms/{id}/preview', [RoomShowController::class, 'preview'])->name('rooms.preview');

Route::prefix('admin')->group(function () {
    Route::middleware(['auth:admin', 'no.cache', 'throttle:admin'])->group(function () {
        // Room Listing per Hotel
        Route::get('/hotels/{hotelId}/rooms', [RoomController::class, 'renderRoomsPerHotel'])->name('manage-rooms');

        // Create & Store
        Route::get('/rooms/create/{hotelId}', [RoomController::class, 'showHotelInformation'])->name('create-room');
        Route::post('/rooms/store/{hotelId}', [RoomController::class, 'storeRoom'])->name('store-room');

        // Edit & Update
        Route::get('/rooms/{id}/edit', [RoomController::class, 'editRoom'])->name('edit-room');
        Route::put('/rooms/{id}', [RoomController::class, 'updateRoom'])->name('update-room');

        // Delete
        Route::delete('/rooms/{id}', [RoomController::class, 'deleteRoom'])->name('delete-room');
    });
});
