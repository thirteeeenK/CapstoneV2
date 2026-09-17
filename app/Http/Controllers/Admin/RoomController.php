<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\AdminAuditService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function renderRoomsPerHotel($hotelID)
    {
        $hotel = HotelModel::findOrFail($hotelID);

        $rooms = $hotel->rooms;

        return view('admin.room.manage-rooms', compact('hotel', 'rooms'));
    }

    public function showHotelInformation($hotelId)
    {
        $hotel = HotelModel::findOrFail($hotelId);

        return view('admin.room.create-room', compact('hotel'));
    }

    public function storeRoom(Request $request, $id, GeminiService $geminiService)
    {
        $hotel = HotelModel::with('destination')->findOrFail($id);

        $request->validate([
            'room_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'view_type' => 'nullable|string|max:255',
            'ideal_guest' => 'nullable|string|max:255',
            'ideal_for' => 'nullable|string|max:255',
            'additional_notes' => 'nullable|string',
            'total_rooms' => 'required|integer|min:1',
            'occupancy' => 'nullable|integer|min:1',
            'base_occupancy' => 'nullable|integer|min:1',
            'max_occupancy' => 'required|integer|min:1',
            'bed_configuration' => 'required|string|max:255',
            'room_size' => 'nullable|string|max:255',
            'base_price' => 'required|numeric|min:0',
            'extra_person_fee' => 'nullable|numeric|min:0',
            'room_amenities' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        // Handle multiple image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $imagePaths[] = $file->store('rooms', 'public');
            }
        }

        // Process room amenities string into array
        $amenities = [];
        if ($request->filled('room_amenities')) {
            $amenities = array_values(array_filter(array_map('trim', explode(',', $request->room_amenities))));
        }

        // Legacy occupancy mirrors max capacity when the hidden field is missing.
        $effectiveMax = $request->max_occupancy;
        $effectiveOccupancy = $request->occupancy ?: $effectiveMax;

        $room = RoomType::create([
            'hotel_id' => $hotel->id,
            'room_name' => $request->room_name,
            'description' => $request->description,
            'view_type' => $request->view_type,
            'ideal_guest' => $request->ideal_guest ?? $request->ideal_for,
            'ideal_for' => $request->ideal_guest ?? $request->ideal_for,
            'additional_notes' => $request->additional_notes,
            'total_rooms' => $request->total_rooms,
            'occupancy' => $effectiveOccupancy,
            'base_occupancy' => $request->base_occupancy ?: 2,
            'max_occupancy' => $effectiveMax,
            'bed_configuration' => $request->bed_configuration,
            'room_size' => $request->room_size,
            'base_price' => $request->base_price,
            'extra_person_fee' => $request->extra_person_fee ?: 0.00,
            'room_amenities' => $amenities,
            'images' => $imagePaths,
            'embedding' => null,
            'is_shown' => $request->has('is_shown') ? $request->boolean('is_shown') : true,
        ]);

        AdminAuditService::log($room);

        // Build structured embedding text and generate normalized vector embedding
        $destinationName = $hotel->destination?->name;
        $embeddingText = $geminiService->buildRoomEmbeddingText($room, $hotel->hotel_name, $destinationName);
        $vector = $geminiService->generateEmbedding($embeddingText, 'RETRIEVAL_DOCUMENT', $room->room_name);

        if ($vector) {
            $room->embedding = $geminiService->formatVectorForDb($vector);
            $room->save();
        }

        // Auto-generate parent hotel embedding if missing
        if (empty($hotel->embedding)) {
            $hotelText = $geminiService->buildHotelEmbeddingText($hotel, $destinationName);
            $hotelVector = $geminiService->generateEmbedding($hotelText, 'RETRIEVAL_DOCUMENT', $hotel->hotel_name);
            if ($hotelVector) {
                $hotel->embedding = $geminiService->formatVectorForDb($hotelVector);
                $hotel->save();
            }
        }

        return redirect()->route('manage-rooms', $hotel->id)->with('success', 'Room details saved successfully!');
    }

    public function editRoom($id)
    {
        $room = RoomType::findOrFail($id);
        $hotel = $room->hotel;

        return view('admin.room.edit-room', compact('room', 'hotel'));
    }

    public function updateRoom(Request $request, $id, GeminiService $geminiService)
    {
        $room = RoomType::findOrFail($id);
        $oldValues = $room->getOriginal();
        $hotel = HotelModel::with('destination')->findOrFail($room->hotel_id);

        $request->validate([
            'room_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'view_type' => 'nullable|string|max:255',
            'ideal_guest' => 'nullable|string|max:255',
            'ideal_for' => 'nullable|string|max:255',
            'additional_notes' => 'nullable|string',
            'total_rooms' => 'required|integer|min:1',
            'occupancy' => 'nullable|integer|min:1',
            'base_occupancy' => 'nullable|integer|min:1',
            'max_occupancy' => 'required|integer|min:1',
            'bed_configuration' => 'required|string|max:255',
            'room_size' => 'nullable|string|max:255',
            'base_price' => 'required|numeric|min:0',
            'extra_person_fee' => 'nullable|numeric|min:0',
            'room_amenities' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'removed_images' => 'nullable|array',
        ]);

        $room->room_name = $request->room_name;
        $room->description = $request->description;
        $room->view_type = $request->view_type;
        $idealGuest = $request->ideal_guest ?? $request->ideal_for;
        $room->setAttribute('ideal_guest', $idealGuest);
        $room->setAttribute('ideal_for', $idealGuest);
        $room->additional_notes = $request->additional_notes;
        $room->total_rooms = $request->total_rooms;
        $room->max_occupancy = $request->max_occupancy;
        $room->occupancy = $request->occupancy ?: $request->max_occupancy;
        $room->base_occupancy = $request->base_occupancy ?: 2;
        $room->bed_configuration = $request->bed_configuration;
        $room->room_size = $request->room_size;
        $room->base_price = $request->base_price;
        $room->extra_person_fee = $request->extra_person_fee ?: 0.00;
        $room->is_shown = $request->has('is_shown') ? $request->boolean('is_shown') : false;

        $amenities = [];
        if ($request->filled('room_amenities')) {
            $amenities = array_values(array_filter(array_map('trim', explode(',', $request->room_amenities))));
        }
        $room->room_amenities = $amenities;

        $currentImages = $room->images;
        if (! is_array($currentImages)) {
            $currentImages = json_decode($currentImages, true) ?? [];
        }

        if ($request->has('removed_images')) {
            $normalize = fn ($p) => str_replace('storage/', '', ltrim((string) $p, '/'));
            $owned = array_map($normalize, $currentImages);
            foreach ($request->removed_images as $removedPath) {
                if (! in_array($normalize($removedPath), $owned, true)) {
                    continue;
                }
                Storage::disk('public')->delete($removedPath);
                $currentImages = array_filter($currentImages, function ($img) use ($normalize, $removedPath) {
                    return $normalize($img) !== $normalize($removedPath);
                });
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('rooms', 'public');
                $currentImages[] = $path;
            }
        }

        $room->images = array_values($currentImages);

        // Build structured embedding text and update normalized vector embedding
        $destinationName = $hotel->destination?->name;
        $embeddingText = $geminiService->buildRoomEmbeddingText($room, $hotel->hotel_name, $destinationName);
        $vector = $geminiService->generateEmbedding($embeddingText, 'RETRIEVAL_DOCUMENT', $room->room_name);

        if ($vector) {
            $room->embedding = $geminiService->formatVectorForDb($vector);
        }

        $room->save();

        AdminAuditService::log($room, $oldValues);

        // Auto-generate parent hotel embedding if missing
        if (empty($hotel->embedding)) {
            $hotelText = $geminiService->buildHotelEmbeddingText($hotel, $destinationName);
            $hotelVector = $geminiService->generateEmbedding($hotelText, 'RETRIEVAL_DOCUMENT', $hotel->hotel_name);
            if ($hotelVector) {
                $hotel->embedding = $geminiService->formatVectorForDb($hotelVector);
                $hotel->save();
            }
        }

        return redirect()->route('manage-rooms', $hotel->id)->with('success', 'Room information updated successfully!');
    }

    public function deleteRoom($id)
    {
        $room = RoomType::findOrFail($id);
        $hotelId = $room->hotel_id;

        $images = $room->images;
        if (is_array($images) && ! empty($images)) {
            foreach ($images as $img) {
                Storage::disk('public')->delete($img);
            }
        }

        AdminAuditService::log($room, $room->getOriginal());

        $room->delete();

        return redirect()->route('manage-rooms', $hotelId)->with('deleted', 'Room deleted successfully.');
    }
}
