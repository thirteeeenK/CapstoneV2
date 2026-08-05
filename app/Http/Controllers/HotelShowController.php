<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesImages;
use App\Models\HotelModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HotelShowController extends Controller
{
    use ResolvesImages;

    public function show(Request $request, $id)
    {
        $isAdmin = Auth::guard('admin')->check();

        $hotel = HotelModel::with(['destination', 'rooms' => function ($query) use ($isAdmin) {
            if (!$isAdmin) {
                $query->where('is_shown', true);
            }
            $query->orderBy('id', 'asc');
        }])->findOrFail($id);

        // If hotel is hidden and visitor is not an admin, return 404
        if (!$hotel->is_shown && !$isAdmin) {
            abort(404, 'Hotel not found or currently unavailable.');
        }

        // Show Admin Preview Banner ONLY if:
        // 1) Explicitly requested via query parameter (?preview=1 or ?preview_room=X)
        // 2) OR the hotel is hidden from public (so admin understands why they can view it)
        $hasPreviewQuery = $request->has('preview') || $request->has('preview_room');
        $isAdminPreview = $isAdmin && ($hasPreviewQuery || !$hotel->is_shown);

        return view('hotel.show', compact('hotel', 'isAdminPreview'));
    }
}
