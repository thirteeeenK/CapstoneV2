<?php

namespace App\Http\Controllers;

use App\Models\DestinationModel;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageShowController extends Controller
{
    /**
     * Display full public catalog listing of all Tour Packages.
     */
    public function index(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $query = Package::where('is_active', true)->with(['destination', 'hotels', 'activities']);

        if ($request->has('destination_id') && ! empty($request->destination_id)) {
            $query->where('destination_id', $request->destination_id);
        }

        $packages = $query->orderBy('price', 'asc')->get();

        return view('package.index', compact('packages', 'destinations'));
    }
}
