<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesImages;
use App\Models\DestinationModel;

class DestinationShowController extends Controller
{
    use ResolvesImages;

    /**
     * Display full catalog listing of all Island Destinations / Sanctuaries.
     */
    public function index()
    {
        $destinations = DestinationModel::withCount([
            'hotels' => function ($query) {
                $query->where('is_shown', true);
            },
            'activities' => function ($query) {
                $query->where('is_shown', true);
            },
        ])->orderBy('name', 'asc')->get();

        return view('destination.index', compact('destinations'));
    }

    /**
     * Display details of a specific destination sanctuary.
     */
    public function show($id)
    {
        $destination = DestinationModel::with([
            'hotels' => function ($query) {
                $query->where('is_shown', true);
            },
            'activities' => function ($query) {
                $query->where('is_shown', true);
            },
        ])->findOrFail($id);

        return view('destination.show', compact('destination'));
    }
}
