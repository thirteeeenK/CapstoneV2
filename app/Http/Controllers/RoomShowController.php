<?php

namespace App\Http\Controllers;

use App\Models\DestinationModel;
use App\Models\RoomType;
use App\Services\Preview\RoomPreviewService;
use Illuminate\Http\Request;

class RoomShowController extends Controller
{
    /**
     * Display full catalog listing of all Rooms & Accommodations.
     */
    public function index(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $query = RoomType::where('is_shown', true)->with(['hotel.destination']);

        if ($request->has('destination_id') && ! empty($request->destination_id)) {
            $destId = $request->destination_id;
            $query->whereHas('hotel', function ($q) use ($destId) {
                $q->where('destination_id', $destId);
            });
        }

        $rooms = $query->orderBy('room_name', 'asc')->get();

        return view('room.index', compact('rooms', 'destinations'));
    }

    /**
     * Return the JSON payload used by the shared room preview modal.
     */
    public function preview($id, RoomPreviewService $service)
    {
        $room = RoomType::with(['reviews', 'reviewSummary'])->find($id);

        if (! $room || (! $room->is_shown && ! auth('admin')->check())) {
            abort(404);
        }

        return response()->json($service->build($room));
    }
}
