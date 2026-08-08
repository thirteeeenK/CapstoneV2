<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesImages;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Services\Preview\ActivityPreviewService;
use Illuminate\Http\Request;

class ActivityShowController extends Controller
{
    use ResolvesImages;

    /**
     * Display full catalog listing of all Activities & Tours.
     */
    public function index(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $query = ActivityModel::where('is_shown', true)->with('destination');

        if ($request->has('destination_id') && !empty($request->destination_id)) {
            $query->where('destination_id', $request->destination_id);
        }

        if ($request->has('level') && !empty($request->level)) {
            $query->where('activity_level', $request->level);
        }

        $activities = $query->orderBy('activity_name', 'asc')->get();

        return view('activity.index', compact('activities', 'destinations'));
    }

    /**
     * Return the JSON payload used by the shared activity preview modal.
     */
    public function preview($id, ActivityPreviewService $service)
    {
        $activity = ActivityModel::with('destination')->find($id);

        if (! $activity || (! $activity->is_shown && ! auth('admin')->check())) {
            abort(404);
        }

        return response()->json($service->build($activity));
    }
}
