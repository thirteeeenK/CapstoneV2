<?php

namespace App\Http\Controllers;

use App\Models\AddOnModel;
use App\Models\DestinationModel;
use Illuminate\Http\Request;

class AddOnShowController extends Controller
{
    /**
     * Display full public catalog listing of all Transfers & Add-ons.
     */
    public function index(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $query = AddOnModel::where('is_shown', true)->with('destination');

        if ($request->has('destination_id') && !empty($request->destination_id)) {
            $query->where('destination_id', $request->destination_id);
        }

        if ($request->has('type') && !empty($request->type)) {
            $query->where('type', 'ilike', '%' . $request->type . '%');
        }

        $addons = $query->orderBy('name', 'asc')->get();

        return view('addon.index', compact('addons', 'destinations'));
    }
}
