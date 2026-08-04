<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Services\GeminiService;

class AddOnController extends Controller
{
    public function index(Request $request)
    {
        $destinations = DestinationModel::all();
        $selectedDestinationId = $request->query('destination_id');
        $search = $request->query('search');

        $query = AddOnModel::with('destination');

        if ($selectedDestinationId) {
            $query->where('destination_id', $selectedDestinationId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $addons = $query->get();

        return view('admin.addons.index', compact('addons', 'destinations', 'selectedDestinationId', 'search'));
    }

    public function create()
    {
        $destinations = DestinationModel::all();
        return view('admin.addons.create', compact('destinations'));
    }

    public function store(Request $request, GeminiService $geminiService)
    {
        $validated = $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'inclusions' => 'nullable|array',
            'pricing_tiers' => 'nullable|array',
            'surcharges' => 'nullable|array',
            'is_shown' => 'nullable|boolean',
        ]);

        $validated['is_shown'] = $request->has('is_shown');

        $addon = AddOnModel::create($validated);

        // Generate AI Vector Embedding
        $text = $geminiService->buildAddOnEmbeddingText($addon);
        $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $addon->name);
        if ($vector) {
            $addon->embedding = $geminiService->formatVectorForDb($vector);
            $addon->save();
        }

        return redirect()->route('admin.addons.index')->with('success', 'Add-on created successfully.');
    }

    public function edit(AddOnModel $addon)
    {
        $destinations = DestinationModel::all();
        return view('admin.addons.edit', compact('addon', 'destinations'));
    }

    public function update(Request $request, AddOnModel $addon, GeminiService $geminiService)
    {
        $validated = $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'inclusions' => 'nullable|array',
            'pricing_tiers' => 'nullable|array',
            'surcharges' => 'nullable|array',
            'is_shown' => 'nullable|boolean',
        ]);

        $validated['is_shown'] = $request->has('is_shown');

        $addon->update($validated);

        // Generate/Update AI Vector Embedding
        $text = $geminiService->buildAddOnEmbeddingText($addon);
        $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $addon->name);
        if ($vector) {
            $addon->embedding = $geminiService->formatVectorForDb($vector);
            $addon->save();
        }

        return redirect()->route('admin.addons.index')->with('success', 'Add-on updated successfully.');
    }

    public function destroy(AddOnModel $addon)
    {
        $addon->delete();
        return redirect()->route('admin.addons.index')->with('success', 'Add-on deleted successfully.');
    }
}
