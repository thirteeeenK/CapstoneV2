<?php

namespace App\Http\Controllers;

use App\Models\HotelModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HotelShowController extends Controller
{
    public function show($id)
    {
        $hotel = HotelModel::with(['destination', 'rooms' => function ($query) {
            $query->orderBy('id', 'asc');
        }])->findOrFail($id);

        $isAdminPreview = Auth::guard('admin')->check();

        // If hotel is hidden and visitor is not an admin, return 404
        if (!$hotel->is_shown && !$isAdminPreview) {
            abort(404, 'Hotel not found or currently unavailable.');
        }

        return view('hotel.show', compact('hotel', 'isAdminPreview'));
    }
}
