<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'name' => 'required|string|max:150|unique:destinations,name',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'image_url' => 'nullable|url'
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('destinations', 'public');
        } elseif ($request->filled('image_url')) {
            $imagePath = $request->image_url;
        }

        DestinationModel::create([
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.destinations')->with('success', 'Destination added successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:destinations,name,' . $id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'image_url' => 'nullable|url'
        ]);

        $destination = DestinationModel::findOrFail($id);

        $imagePath = $destination->image;
        if ($request->hasFile('image')) {
            if ($imagePath && !str_starts_with($imagePath, 'http')) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('destinations', 'public');
        } elseif ($request->filled('image_url')) {
            $imagePath = $request->image_url;
        }

        $destination->update([
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.destinations')->with('success', 'Destination updated successfully!');
    }

    public function destroy($id)
    {
        $destination = DestinationModel::findOrFail($id);
        if ($destination->image && !str_starts_with($destination->image, 'http')) {
            Storage::disk('public')->delete($destination->image);
        }
        $destination->delete();
        return redirect()->route('admin.destinations')->with('success', 'Destination deleted successfully!');
    }
}
