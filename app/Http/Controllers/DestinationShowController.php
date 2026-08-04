<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesImages;
use App\Models\DestinationModel;

class DestinationShowController extends Controller
{
    use ResolvesImages;

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
