<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'hotels');
        $selectedDestinationId = $request->query('destination_id');
        $visibility = $request->query('visibility'); // 'visible', 'hidden', or null/all
        $search = $request->query('search');

        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        // 1. Unfiltered count stats for overview cards
        $allHotels = HotelModel::all();
        $allRooms = RoomType::all();
        $allActivities = ActivityModel::all();

        $stats = [
            'total_hotels' => $allHotels->count(),
            'shown_hotels' => $allHotels->where('is_shown', true)->count(),
            'hidden_hotels' => $allHotels->where('is_shown', false)->count(),

            'total_rooms' => $allRooms->count(),
            'shown_rooms' => $allRooms->where('is_shown', true)->count(),
            'hidden_rooms' => $allRooms->where('is_shown', false)->count(),

            'total_activities' => $allActivities->count(),
            'shown_activities' => $allActivities->where('is_shown', true)->count(),
            'hidden_activities' => $allActivities->where('is_shown', false)->count(),
        ];

        // 2. Filtered Queries
        // Hotels Query
        $hotelQuery = HotelModel::with('destination');
        if ($selectedDestinationId) {
            $hotelQuery->where('destination_id', $selectedDestinationId);
        }
        if ($visibility === 'visible') {
            $hotelQuery->where('is_shown', true);
        } elseif ($visibility === 'hidden') {
            $hotelQuery->where('is_shown', false);
        }
        if ($search) {
            $hotelQuery->where(function ($q) use ($search) {
                $q->where('hotel_name', 'like', '%' . $search . '%')
                    ->orWhere('specific_address', 'like', '%' . $search . '%');
            });
        }
        $hotels = $hotelQuery->orderBy('id', 'asc')->get();

        // Rooms Query
        $roomQuery = RoomType::with('hotel.destination');
        if ($selectedDestinationId) {
            $roomQuery->whereHas('hotel', function ($q) use ($selectedDestinationId) {
                $q->where('destination_id', $selectedDestinationId);
            });
        }
        if ($visibility === 'visible') {
            $roomQuery->where('is_shown', true);
        } elseif ($visibility === 'hidden') {
            $roomQuery->where('is_shown', false);
        }
        if ($search) {
            $roomQuery->where(function ($q) use ($search) {
                $q->where('room_name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        $rooms = $roomQuery->orderBy('id', 'asc')->get();

        // Activities Query
        $activityQuery = ActivityModel::with('destination');
        if ($selectedDestinationId) {
            $activityQuery->where('destination_id', $selectedDestinationId);
        }
        if ($visibility === 'visible') {
            $activityQuery->where('is_shown', true);
        } elseif ($visibility === 'hidden') {
            $activityQuery->where('is_shown', false);
        }
        if ($search) {
            $activityQuery->where(function ($q) use ($search) {
                $q->where('activity_name', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }
        $activities = $activityQuery->orderBy('id', 'asc')->get();

        return view('admin.inventory.index', compact(
            'hotels',
            'rooms',
            'activities',
            'destinations',
            'stats',
            'activeTab',
            'selectedDestinationId',
            'visibility',
            'search'
        ));
    }

    public function toggleVisibility(Request $request)
    {
        $request->validate([
            'type' => 'required|in:hotel,room,activity',
            'id' => 'required|integer',
            'is_shown' => 'required|boolean'
        ]);

        $type = $request->type;
        $id = $request->id;
        $isShown = $request->boolean('is_shown');

        if ($type === 'hotel') {
            $item = HotelModel::findOrFail($id);
        } elseif ($type === 'room') {
            $item = RoomType::findOrFail($id);
        } else {
            $item = ActivityModel::findOrFail($id);
        }

        $item->is_shown = $isShown;
        $item->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => ucfirst($type) . ' visibility updated successfully.',
                'is_shown' => $item->is_shown,
            ]);
        }

        return redirect()->back()->with('success', ucfirst($type) . ' visibility updated successfully.');
    }
}
