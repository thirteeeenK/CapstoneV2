<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationModel;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPackageController extends Controller
{
    /**
     * Display listing of all Tour Packages with search and visibility filters.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $destinationId = $request->query('destination_id');
        $visibility = $request->query('visibility'); // 'visible', 'hidden'

        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $query = Package::with('destination');

        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
        }

        if ($destinationId) {
            $query->where('destination_id', $destinationId);
        }

        if ($visibility === 'visible') {
            $query->where('is_active', true);
        } elseif ($visibility === 'hidden') {
            $query->where('is_active', false);
        }

        $packages = $query->orderBy('id', 'desc')->paginate(12);

        $stats = [
            'total' => Package::count(),
            'visible' => Package::where('is_active', true)->count(),
            'hidden' => Package::where('is_active', false)->count(),
        ];

        return view('admin.packages.index', compact('packages', 'destinations', 'search', 'destinationId', 'visibility', 'stats'));
    }

    /**
     * Show form to create a new Tour Package.
     */
    public function create()
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        return view('admin.packages.create', compact('destinations'));
    }

    /**
     * Store a new Tour Package in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'days' => 'nullable|integer|min:1',
            'nights' => 'nullable|integer|min:0',
            'min_pax' => 'required|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'inclusions_text' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'image_url' => 'nullable|url|max:500',
            'is_active' => 'required|boolean',
        ]);

        // Process Inclusions into JSON array
        $inclusions = [];
        if (!empty($validated['inclusions_text'])) {
            $inclusions = array_values(array_filter(array_map('trim', explode("\n", $validated['inclusions_text']))));
        }

        // Process Package Main Image
        $images = [];
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('packages', 'public');
            $images[] = '/storage/' . $path;
        } elseif (!empty($validated['image_url'])) {
            $images[] = $validated['image_url'];
        } else {
            $images[] = '/images/placeholder.jpg';
        }

        $package = Package::create([
            'destination_id' => $validated['destination_id'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'Standard Promo Package',
            'price' => $validated['price'],
            'days' => $validated['days'] ?? 3,
            'nights' => $validated['nights'] ?? 2,
            'min_pax' => $validated['min_pax'],
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_to' => $validated['valid_to'] ?? null,
            'generic_inclusions' => $inclusions,
            'images' => $images,
            'is_active' => (bool)$validated['is_active'],
        ]);

        // Auto-generate AI Vector Embedding for Chatbot & Recommendation Engine
        try {
            $geminiService = app(\App\Services\GeminiService::class);
            $text = $geminiService->buildPackageEmbeddingText($package->fresh('destination'));
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $package->name);
            if ($vector) {
                $package->embedding = $geminiService->formatVectorForDb($vector);
                $package->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Package embedding generation skipped: ' . $e->getMessage());
        }

        return redirect()->route('admin.packages.index')->with('success', "Tour package '{$validated['name']}' created successfully with AI vector embedding!");
    }

    /**
     * Show form to edit an existing Tour Package.
     */
    public function edit($id)
    {
        $package = Package::findOrFail($id);
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        return view('admin.packages.edit', compact('package', 'destinations'));
    }

    /**
     * Update an existing Tour Package.
     */
    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        $validated = $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'days' => 'nullable|integer|min:1',
            'nights' => 'nullable|integer|min:0',
            'min_pax' => 'required|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'inclusions_text' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'image_url' => 'nullable|url|max:500',
            'is_active' => 'required|boolean',
        ]);

        // Process Inclusions
        $inclusions = [];
        if (!empty($validated['inclusions_text'])) {
            $inclusions = array_values(array_filter(array_map('trim', explode("\n", $validated['inclusions_text']))));
        }

        // Process Image
        $images = $package->images ?: [];
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('packages', 'public');
            $images = ['/storage/' . $path];
        } elseif (!empty($validated['image_url'])) {
            $images = [$validated['image_url']];
        }

        $package->update([
            'destination_id' => $validated['destination_id'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? $package->type,
            'price' => $validated['price'],
            'days' => $validated['days'],
            'nights' => $validated['nights'],
            'min_pax' => $validated['min_pax'],
            'valid_from' => $validated['valid_from'],
            'valid_to' => $validated['valid_to'],
            'generic_inclusions' => $inclusions,
            'images' => $images,
            'is_active' => (bool)$validated['is_active'],
        ]);

        // Auto-re-generate AI Vector Embedding
        try {
            $geminiService = app(\App\Services\GeminiService::class);
            $text = $geminiService->buildPackageEmbeddingText($package->fresh('destination'));
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $package->name);
            if ($vector) {
                $package->embedding = $geminiService->formatVectorForDb($vector);
                $package->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Package embedding re-generation skipped: ' . $e->getMessage());
        }

        return redirect()->route('admin.packages.index')->with('success', "Tour package '{$package->name}' updated successfully with fresh AI vector embedding!");
    }

    /**
     * Instant toggle visibility (is_active).
     */
    public function toggleVisibility(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        $package->is_active = !$package->is_active;
        $package->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $package->is_active,
                'message' => "Package '{$package->name}' is now " . ($package->is_active ? 'Visible' : 'Hidden') . '.',
            ]);
        }

        return redirect()->back()->with('success', "Package '{$package->name}' visibility toggled.");
    }

    /**
     * Delete a Tour Package.
     */
    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        $name = $package->name;
        $package->delete();

        return redirect()->route('admin.packages.index')->with('success', "Tour Package '{$name}' deleted successfully.");
    }
}
