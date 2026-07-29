<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationModel;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index()
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        return view('admin.destinations', compact('destinations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150|unique:destinations,name'
        ]);

        DestinationModel::create([
            'name' => $request->name
        ]);

        return redirect()->route('admin.destinations')->with('success', 'Destination added successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:destinations,name,' . $id
        ]);

        $destination = DestinationModel::findOrFail($id);
        $destination->update([
            'name' => $request->name
        ]);

        return redirect()->route('admin.destinations')->with('success', 'Destination updated successfully!');
    }

    public function destroy($id)
    {
        $destination = DestinationModel::findOrFail($id);
        $destination->delete();
        return redirect()->route('admin.destinations')->with('success', 'Destination deleted successfully!');
    }
}
