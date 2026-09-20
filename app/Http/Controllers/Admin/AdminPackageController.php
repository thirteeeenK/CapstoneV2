<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\AdminAuditService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

        $query = Package::with('destination')->withCount(['hotels', 'activities', 'addOns']);

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%")
                ->orWhere('type', 'ilike', "%{$search}%");
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

        if ($request->ajax()) {
            return view('admin.packages._table', compact('packages', 'destinations', 'search', 'destinationId', 'visibility'));
        }

        return view('admin.packages.index', compact('packages', 'destinations', 'search', 'destinationId', 'visibility', 'stats'));
    }

    /**
     * Show form to create a new Tour Package.
     */
    public function create()
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        $hotels = HotelModel::with('rooms')->orderBy('hotel_name', 'asc')->get();
        $activities = ActivityModel::orderBy('activity_name', 'asc')->get();
        $addOns = AddOnModel::orderBy('name', 'asc')->get();

        return view('admin.packages.create', compact('destinations', 'hotels', 'activities', 'addOns'));
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
            'inclusions' => 'nullable|array',
            'inclusions.*' => 'nullable|string|max:255',
            'inclusions_text' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'image_url' => 'nullable|url|max:500',
            'is_active' => 'required|boolean',
            'hotel_ids' => 'nullable|array',
            'hotel_ids.*' => 'exists:hotels,id',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:rooms,id',
            'activity_ids' => 'nullable|array',
            'activity_ids.*' => 'exists:activities,id',
            'add_on_ids' => 'nullable|array',
            'add_on_ids.*' => 'exists:add_ons,id',
        ]);

        // Fail fast before the package row is created.
        $this->validateRelationMatches($validated);

        // Process Inclusions into JSON array (one input row per item; legacy textarea fallback)
        $inclusions = [];
        if (! empty($validated['inclusions']) && is_array($validated['inclusions'])) {
            $inclusions = array_values(array_filter(array_map('trim', $validated['inclusions'])));
        } elseif (! empty($validated['inclusions_text'])) {
            $inclusions = array_values(array_filter(array_map('trim', explode("\n", $validated['inclusions_text']))));
        }

        // Process Package Main Image
        $images = [];
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('packages', 'public');
            $images[] = '/storage/'.$path;
        } elseif (! empty($validated['image_url'])) {
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
            'is_active' => (bool) $validated['is_active'],
        ]);

        $this->syncPackageRelations($package, $validated);

        AdminAuditService::log($package);

        // Auto-generate AI Vector Embedding for Chatbot & Recommendation Engine
        try {
            $geminiService = app(GeminiService::class);
            $text = $geminiService->buildPackageEmbeddingText($package->fresh(['destination', 'hotels', 'rooms', 'activities', 'addOns']));
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $package->name);
            if ($vector) {
                $package->embedding = $geminiService->formatVectorForDb($vector);
                $package->save();
            }
        } catch (\Exception $e) {
            Log::warning('Package embedding generation skipped: '.$e->getMessage());
        }

        return redirect()->route('admin.packages.index')->with('success', "Tour package '{$validated['name']}' created successfully with AI vector embedding!");
    }

    /**
     * Show form to edit an existing Tour Package.
     */
    public function edit($id)
    {
        $package = Package::with(['hotels', 'rooms', 'activities', 'addOns'])->findOrFail($id);
        $destinations = DestinationModel::orderBy('name', 'asc')->get();
        $hotels = HotelModel::with('rooms')->orderBy('hotel_name', 'asc')->get();
        $activities = ActivityModel::orderBy('activity_name', 'asc')->get();
        $addOns = AddOnModel::orderBy('name', 'asc')->get();

        return view('admin.packages.edit', compact('package', 'destinations', 'hotels', 'activities', 'addOns'));
    }

    /**
     * Update an existing Tour Package.
     */
    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        $oldValues = $package->getOriginal();

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
            'inclusions' => 'nullable|array',
            'inclusions.*' => 'nullable|string|max:255',
            'inclusions_text' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'image_url' => 'nullable|url|max:500',
            'is_active' => 'required|boolean',
            'hotel_ids' => 'nullable|array',
            'hotel_ids.*' => 'exists:hotels,id',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:rooms,id',
            'activity_ids' => 'nullable|array',
            'activity_ids.*' => 'exists:activities,id',
            'add_on_ids' => 'nullable|array',
            'add_on_ids.*' => 'exists:add_ons,id',
        ]);

        // Fail fast before the package row is touched.
        $this->validateRelationMatches($validated);

        // Process Inclusions (one input row per item; legacy textarea fallback)
        $inclusions = [];
        if (! empty($validated['inclusions']) && is_array($validated['inclusions'])) {
            $inclusions = array_values(array_filter(array_map('trim', $validated['inclusions'])));
        } elseif (! empty($validated['inclusions_text'])) {
            $inclusions = array_values(array_filter(array_map('trim', explode("\n", $validated['inclusions_text']))));
        }

        // Process Image
        $images = $package->images ?: [];
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('packages', 'public');
            $images = ['/storage/'.$path];
        } elseif (! empty($validated['image_url'])) {
            $images = [$validated['image_url']];
        }

        $package->update([
            'destination_id' => $validated['destination_id'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? $package->type,
            'price' => $validated['price'],
            'days' => $validated['days'] ?? $package->days,
            'nights' => $validated['nights'] ?? $package->nights,
            'min_pax' => $validated['min_pax'],
            'valid_from' => $validated['valid_from'] ?? $package->valid_from,
            'valid_to' => $validated['valid_to'] ?? $package->valid_to,
            'generic_inclusions' => $inclusions,
            'images' => $images,
            'is_active' => (bool) $validated['is_active'],
        ]);

        $this->syncPackageRelations($package, $validated);

        AdminAuditService::log($package, $oldValues);

        // Auto-re-generate AI Vector Embedding
        try {
            $geminiService = app(GeminiService::class);
            $text = $geminiService->buildPackageEmbeddingText($package->fresh(['destination', 'hotels', 'rooms', 'activities', 'addOns']));
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $package->name);
            if ($vector) {
                $package->embedding = $geminiService->formatVectorForDb($vector);
                $package->save();
            }
        } catch (\Exception $e) {
            Log::warning('Package embedding re-generation skipped: '.$e->getMessage());
        }

        return redirect()->route('admin.packages.index')->with('success', "Tour package '{$package->name}' updated successfully with fresh AI vector embedding!");
    }

    /**
     * Guard: every chosen room must belong to a chosen hotel (max one room per hotel).
     *
     * @throws ValidationException
     */
    protected function validateRelationMatches(array $validated): void
    {
        $hotelIds = collect($validated['hotel_ids'] ?? [])->map(fn ($id) => (int) $id);
        $rooms = RoomType::whereIn('id', $validated['room_ids'] ?? [])->get();

        $stray = $rooms->reject(fn ($room) => $hotelIds->contains((int) $room->hotel_id));
        if ($stray->isNotEmpty()) {
            throw ValidationException::withMessages([
                'room_ids' => 'Each room must belong to one of the selected hotels: '.$stray->pluck('room_name')->join(', ').'.',
            ]);
        }
        $dupes = $rooms->groupBy('hotel_id')->filter(fn ($group) => $group->count() > 1);
        if ($dupes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'room_ids' => 'Only one room per hotel is allowed.',
            ]);
        }
    }

    /**
     * Sync the package's linked hotels (+room per hotel), activities and add-ons.
     * Price stays a manual admin flat — relations are descriptive links, never summed.
     *
     * @throws ValidationException
     */
    protected function syncPackageRelations(Package $package, array $validated): void
    {
        $this->validateRelationMatches($validated);

        $hotelIds = collect($validated['hotel_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $rooms = RoomType::whereIn('id', $validated['room_ids'] ?? [])->get();

        $hotelSync = [];
        foreach ($hotelIds as $hotelId) {
            $hotelSync[$hotelId] = ['room_type_id' => null];
        }
        foreach ($rooms as $room) {
            $hotelSync[(int) $room->hotel_id] = ['room_type_id' => $room->id];
        }
        $package->hotels()->sync($hotelSync);
        $package->activities()->sync($validated['activity_ids'] ?? []);
        $package->addOns()->sync($validated['add_on_ids'] ?? []);
    }

    /**
     * Instant toggle visibility (is_active).
     */
    public function toggleVisibility(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        $oldValues = ['is_active' => $package->is_active];
        $package->is_active = ! $package->is_active;
        $package->save();

        AdminAuditService::log($package, $oldValues);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $package->is_active,
                'message' => "Package '{$package->name}' is now ".($package->is_active ? 'Visible' : 'Hidden').'.',
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

        AdminAuditService::log($package, $package->getOriginal());

        $package->delete();

        return redirect()->route('admin.packages.index')->with('success', "Tour Package '{$name}' deleted successfully.");
    }
}
