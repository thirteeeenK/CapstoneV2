<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesImages;
use App\Models\HotelModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HotelShowController extends Controller
{
    use ResolvesImages;

    public function show($id)
    {
        $isAdminPreview = Auth::guard('admin')->check();

        $hotel = HotelModel::with(['destination', 'rooms' => function ($query) use ($isAdminPreview) {
            if (!$isAdminPreview) {
                $query->where('is_shown', true);
            }
            $query->orderBy('id', 'asc');
        }])->findOrFail($id);

        // If hotel is hidden and visitor is not an admin, return 404
        if (!$hotel->is_shown && !$isAdminPreview) {
            abort(404, 'Hotel not found or currently unavailable.');
        }

        return view('hotel.show', compact('hotel', 'isAdminPreview'));
    }
}
