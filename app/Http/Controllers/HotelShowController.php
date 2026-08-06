<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesImages;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HotelShowController extends Controller
{
    use ResolvesImages;

    /**
     * Display full catalog listing of all Hotels & Sanctuary Stays.
     */
    public function index(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $query = HotelModel::where('is_shown', true)->with(['destination', 'rooms']);

        if ($request->has('destination_id') && !empty($request->destination_id)) {
            $query->where('destination_id', $request->destination_id);
        }

        $hotels = $query->orderBy('hotel_name', 'asc')->get();

        return view('hotel.index', compact('hotels', 'destinations'));
    }

    /**
     * Display dynamic details for a specific Hotel / Sanctuary.
     */
    public function show(Request $request, $id)
    {
        $isAdmin = Auth::guard('admin')->check();

        $hotel = HotelModel::with([
            'destination',
            'rooms' => function ($query) use ($isAdmin) {
                if (!$isAdmin) {
                    $query->where('is_shown', true);
                }
                $query->orderBy('id', 'asc')->with('reviews.user');
            },
            'reviews.user',
            'reviewSummary',
        ])->findOrFail($id);

        // If hotel is hidden and visitor is not an admin, return 404
        if (!$hotel->is_shown && !$isAdmin) {
            abort(404, 'Hotel not found or currently unavailable.');
        }

        // Show Admin Preview Banner ONLY if explicitly requested or hidden
        $hasPreviewQuery = $request->has('preview') || $request->has('preview_room');
        $isAdminPreview = $isAdmin && ($hasPreviewQuery || !$hotel->is_shown);

        return view('hotel.show', compact('hotel', 'isAdminPreview'));
    }
}
