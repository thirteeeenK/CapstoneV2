<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\GeminiService;
class HotelController extends Controller
{
    public function create()
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        return view('admin.hotel.add_hotel', compact('destinations'));
    }

    public function store(Request $request, GeminiService $geminiService)
    {
        // Standard Blade Validation 
        $request->validate([
            'hotel_name' => 'required|string|max:255',
            'type' => 'required',
            'hotel_description' => 'required|string',
            'destination_id' => 'required|exists:destinations,id',
            'specific_address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        // handle multiple file uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $imagePaths[] = $file->store('hotels', 'public');
            }
        }

        $vibeTags = $request->vibe_tags
            ? array_values(array_filter(array_map('trim', explode(',', $request->vibe_tags))))
            : null;
        $featuredAmenities = $request->featured_amenities
            ? array_values(array_filter(array_map('trim', explode(',', $request->featured_amenities))))
            : null;

        // store to the database
        $hotel = HotelModel::create([
            'hotel_name' => $request->hotel_name,
            'type' => $request->type,
            'vibe_tags' => $vibeTags,
            'featured_amenities' => $featuredAmenities,
            'hotel_description' => $request->hotel_description,
            'destination_id' => $request->destination_id,
            'specific_address' => $request->specific_address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'images' => $imagePaths,
            'is_shown' => $request->has('is_shown') ? $request->boolean('is_shown') : true,
        ]);

        // Retrieve destination name for semantic grounding
        $destination = DestinationModel::find($request->destination_id);
        $destinationName = $destination ? $destination->name : null;

        // Build structured text and generate normalized vector embedding
        $embeddingText = $geminiService->buildHotelEmbeddingText($hotel, $destinationName);
        $vector = $geminiService->generateEmbedding($embeddingText, 'RETRIEVAL_DOCUMENT', $hotel->hotel_name);

        if ($vector) {
            $hotel->embedding = $geminiService->formatVectorForDb($vector);
            $hotel->save();
        }

        return redirect()->back()->with('success', 'Hotel Information Added Successfully.');
    }

    public function showListing(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        $selectedDestinationId = $request->query('destination_id');
        $search = $request->query('search');

        $query = HotelModel::with('destination');

        if ($selectedDestinationId) {
            $query->where('destination_id', $selectedDestinationId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('hotel_name', 'like', '%' . $search . '%')
                    ->orWhere('specific_address', 'like', '%' . $search . '%');
            });
        }

        $hotels = $query->orderBy('destination_id', 'asc')->orderBy('id', 'asc')->get();

        return view('admin.hotel.hotel_listings', compact('hotels', 'destinations', 'selectedDestinationId', 'search'));
    }

    public function edit($id)
    {
        $hotel = HotelModel::findOrFail($id);

        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        return view('admin.hotel.edit_hotel', compact('hotel', 'destinations'));
    }

    public function editInformation(Request $request, $id, GeminiService $geminiService)
    {
        // Validation
        $request->validate([
            'hotel_name' => 'required|string|max:255',
            'type' => 'required',
            'hotel_description' => 'required|string',
            'destination_id' => 'required|exists:destinations,id',
            'specific_address' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'removed_images' => 'nullable|array'
        ]);

        $hotel = HotelModel::findOrFail($id);

        $vibeTags = $request->vibe_tags
            ? array_values(array_filter(array_map('trim', explode(',', $request->vibe_tags))))
            : null;
        $featuredAmenities = $request->featured_amenities
            ? array_values(array_filter(array_map('trim', explode(',', $request->featured_amenities))))
            : null;

        $hotel->hotel_name = $request->hotel_name;
        $hotel->type = $request->type;
        $hotel->vibe_tags = $vibeTags;
        $hotel->featured_amenities = $featuredAmenities;
        $hotel->hotel_description = $request->hotel_description;
        $hotel->destination_id = $request->destination_id;
        $hotel->specific_address = $request->specific_address;
        $hotel->latitude = $request->latitude;
        $hotel->longitude = $request->longitude;
        $hotel->is_shown = $request->has('is_shown') ? $request->boolean('is_shown') : false;

        $currentImages = $hotel->images;
        if (!is_array($currentImages)) {
            $currentImages = json_decode($currentImages, true) ?? [];
        }

        if ($request->has('removed_images')) {
            foreach ($request->removed_images as $removedPath) {
                Storage::disk('public')->delete($removedPath);
                $currentImages = array_filter($currentImages, function ($img) use ($removedPath) {
                    return $img !== $removedPath;
                });
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('hotels', 'public');
                $currentImages[] = $path;
            }
        }

        $hotel->images = array_values($currentImages);

        // Retrieve destination name for semantic grounding
        $destination = DestinationModel::find($hotel->destination_id);
        $destinationName = $destination ? $destination->name : null;

        // Build structured text and update normalized vector embedding
        $embeddingText = $geminiService->buildHotelEmbeddingText($hotel, $destinationName);
        $vector = $geminiService->generateEmbedding($embeddingText, 'RETRIEVAL_DOCUMENT', $hotel->hotel_name);
        if ($vector) {
            $hotel->embedding = $geminiService->formatVectorForDb($vector);
        }

        $hotel->save();

        return redirect()->back()->with('success', 'Hotel information updated successfully!');
    }

    public function delete($id)
    {
        $hotel = HotelModel::findOrFail($id);

        // no need to json decode since naka cast na as array yung images sa model.
        $images = $hotel->images;
        if (is_array($images) && !empty($images)) {
            foreach ($images as $img) {
                Storage::disk('public')->delete($img);
            }
        }

        $hotel->delete();

        return redirect()->back()->with('deleted', 'Hotel Information Deleted Successfully');
    }
}
