<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\AdminAuditService;
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
        $allAddons = AddOnModel::all();

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

            'total_addons' => $allAddons->count(),
            'shown_addons' => $allAddons->where('is_shown', true)->count(),
            'hidden_addons' => $allAddons->where('is_shown', false)->count(),
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
                $q->where('hotel_name', 'ilike', '%'.$search.'%')
                    ->orWhere('specific_address', 'ilike', '%'.$search.'%');
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
                $q->where('room_name', 'ilike', '%'.$search.'%')
                    ->orWhere('description', 'ilike', '%'.$search.'%');
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
                $q->where('activity_name', 'ilike', '%'.$search.'%')
                    ->orWhere('category', 'ilike', '%'.$search.'%')
                    ->orWhere('notes', 'ilike', '%'.$search.'%');
            });
        }
        $activities = $activityQuery->orderBy('id', 'asc')->get();

        // Add-ons & Transfers Query
        $addonQuery = AddOnModel::with('destination');
        if ($selectedDestinationId) {
            $addonQuery->where('destination_id', $selectedDestinationId);
        }
        if ($visibility === 'visible') {
            $addonQuery->where('is_shown', true);
        } elseif ($visibility === 'hidden') {
            $addonQuery->where('is_shown', false);
        }
        if ($search) {
            $addonQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('type', 'ilike', '%'.$search.'%')
                    ->orWhere('description', 'ilike', '%'.$search.'%');
            });
        }
        $addons = $addonQuery->orderBy('id', 'asc')->get();

        if ($request->ajax()) {
            return view('admin.inventory._results', compact(
                'hotels',
                'rooms',
                'activities',
                'addons',
                'destinations',
                'activeTab',
                'selectedDestinationId',
                'visibility',
                'search'
            ));
        }

        return view('admin.inventory.index', compact(
            'hotels',
            'rooms',
            'activities',
            'addons',
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
            'type' => 'required|in:hotel,room,activity,addon,add_on,package',
            'id' => 'required|integer',
            'is_shown' => 'required|boolean',
        ]);

        $type = $request->type;
        $id = $request->id;
        $isShown = $request->boolean('is_shown');

        if ($type === 'hotel') {
            $item = HotelModel::findOrFail($id);
        } elseif ($type === 'room') {
            $item = RoomType::findOrFail($id);
        } elseif ($type === 'addon' || $type === 'add_on') {
            $item = AddOnModel::findOrFail($id);
        } elseif ($type === 'package') {
            $item = Package::findOrFail($id);
        } else {
            $item = ActivityModel::findOrFail($id);
        }

        $oldValues = ['is_shown' => $item->is_shown];

        $item->is_shown = $isShown;
        $item->save();

        AdminAuditService::log($item, $oldValues);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => ucfirst($type).' visibility updated successfully.',
                'is_shown' => $item->is_shown,
            ]);
        }

        return redirect()->back()->with('success', ucfirst($type).' visibility updated successfully.');
    }
}
