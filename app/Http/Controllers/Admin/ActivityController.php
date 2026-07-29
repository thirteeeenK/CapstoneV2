<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        $selectedDestinationId = $request->query('destination_id');
        $search = $request->query('search');
        $selectedCategory = $request->query('category');

        $query = ActivityModel::with('destination');

        if ($selectedDestinationId) {
            $query->where('destination_id', $selectedDestinationId);
        }

        if ($selectedCategory) {
            $query->where('category', $selectedCategory);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('activity_name', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('activity_level', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }

        $activities = $query->orderBy('destination_id', 'asc')->orderBy('id', 'asc')->get();

        return view('admin.activities.index', compact(
            'activities',
            'destinations',
            'selectedDestinationId',
            'search',
            'selectedCategory'
        ));
    }

    public function create()
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        return view('admin.activities.create', compact('destinations'));
    }

    public function store(Request $request, GeminiService $geminiService)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'activity_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'activity_level' => 'required|string|max:255',
            'rate' => 'required|string|max:255',
            'duration' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'requirements' => 'nullable|string',
            'description' => 'nullable|string',
            'vibe_tags' => 'nullable|string',
            'ideal_for' => 'nullable|string',
            'notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $imagePaths[] = $file->store('activities', 'public');
            }
        }

        $vibeTags = $request->vibe_tags
            ? array_values(array_filter(array_map('trim', explode(',', $request->vibe_tags))))
            : null;

        $activity = ActivityModel::create([
            'destination_id' => $request->destination_id,
            'activity_name' => $request->activity_name,
            'category' => $request->category,
            'activity_level' => $request->activity_level,
            'rate' => $request->rate,
            'duration' => $request->duration,
            'capacity' => $request->capacity,
            'requirements' => $request->requirements,
            'description' => $request->description,
            'vibe_tags' => $vibeTags,
            'ideal_for' => $request->ideal_for,
            'notes' => $request->notes,
            'images' => $imagePaths,
        ]);

        // Retrieve destination name for semantic grounding
        $destination = DestinationModel::find($request->destination_id);
        $destinationName = $destination ? $destination->name : null;

        // Build structured text and generate normalized vector embedding
        $embeddingText = $geminiService->buildActivityEmbeddingText($activity, $destinationName);
        $vector = $geminiService->generateEmbedding($embeddingText, 'RETRIEVAL_DOCUMENT', $activity->activity_name);

        if ($vector) {
            $activity->embedding = $geminiService->formatVectorForDb($vector);
            $activity->save();
        }

        return redirect()->route('admin.activities.index')->with('success', 'Activity added successfully.');
    }

    public function edit($id)
    {
        $activity = ActivityModel::findOrFail($id);
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        return view('admin.activities.edit', compact('activity', 'destinations'));
    }

    public function update(Request $request, $id, GeminiService $geminiService)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'activity_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'activity_level' => 'required|string|max:255',
            'rate' => 'required|string|max:255',
            'duration' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'requirements' => 'nullable|string',
            'description' => 'nullable|string',
            'vibe_tags' => 'nullable|string',
            'ideal_for' => 'nullable|string',
            'notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'removed_images' => 'nullable|array'
        ]);

        $activity = ActivityModel::findOrFail($id);

        $vibeTags = $request->vibe_tags
            ? array_values(array_filter(array_map('trim', explode(',', $request->vibe_tags))))
            : null;

        $activity->destination_id = $request->destination_id;
        $activity->activity_name = $request->activity_name;
        $activity->category = $request->category;
        $activity->activity_level = $request->activity_level;
        $activity->rate = $request->rate;
        $activity->duration = $request->duration;
        $activity->capacity = $request->capacity;
        $activity->requirements = $request->requirements;
        $activity->description = $request->description;
        $activity->vibe_tags = $vibeTags;
        $activity->ideal_for = $request->ideal_for;
        $activity->notes = $request->notes;

        $currentImages = $activity->images;
        if (!is_array($currentImages)) {
            $currentImages = json_decode($currentImages, true) ?? [];
        }

        if ($request->has('removed_images')) {
            foreach ($request->removed_images as $removedPath) {
                Storage::disk('public')->delete($removedPath);
                $currentImages = array_filter($currentImages, function ($img) use ($removedPath) {
                    return $img !== $removedPath;
                });
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('activities', 'public');
                $currentImages[] = $path;
            }
        }

        $activity->images = array_values($currentImages);

        // Retrieve destination name for semantic grounding
        $destination = DestinationModel::find($activity->destination_id);
        $destinationName = $destination ? $destination->name : null;

        // Build structured text and regenerate vector embedding
        $embeddingText = $geminiService->buildActivityEmbeddingText($activity, $destinationName);
        $vector = $geminiService->generateEmbedding($embeddingText, 'RETRIEVAL_DOCUMENT', $activity->activity_name);

        if ($vector) {
            $activity->embedding = $geminiService->formatVectorForDb($vector);
        }

        $activity->save();

        return redirect()->route('admin.activities.index')->with('success', 'Activity updated successfully.');
    }

    public function destroy($id)
    {
        $activity = ActivityModel::findOrFail($id);

        $images = $activity->images;
        if (is_array($images) && !empty($images)) {
            foreach ($images as $img) {
                Storage::disk('public')->delete($img);
            }
        }

        $activity->delete();

        return redirect()->route('admin.activities.index')->with('deleted', 'Activity deleted successfully.');
    }
}
