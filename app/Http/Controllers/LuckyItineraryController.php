<?php

namespace App\Http\Controllers;

use App\Models\DestinationModel;
use App\Services\CartService;
use App\Services\LuckyItineraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LuckyItineraryController extends Controller
{
    public function __construct(
        protected LuckyItineraryService $luckyService,
        protected CartService $cartService,
    ) {}

    /**
     * Display the "I'm Feeling Lucky" generator page.
     */
    public function index()
    {
        $destinations = DestinationModel::orderBy('name')->get(['id', 'name', 'region']);

        return view('lucky.index', compact('destinations'));
    }

    /**
     * Generate a random surprise itinerary within the applied filters.
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => 'nullable|integer|exists:destinations,id',
            'max_budget' => 'nullable|numeric|min:1',
            'activity_count' => 'nullable|integer|min:1|max:5',
            'nights' => 'nullable|integer|min:1|max:7',
            'hotel_category' => 'nullable|in:budget,mid,luxury',
            'pax' => 'nullable|integer|min:1|max:4',
            'activity_level' => 'nullable|string|max:50',
            'activity_category' => 'nullable|string|max:50',
            'start_date' => 'nullable|date|after_or_equal:today',
        ]);

        $result = $this->luckyService->generate($validated);

        if (isset($result['message'])) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json(['success' => true, 'itinerary' => $result]);
    }

    /**
     * Accept a generated itinerary: recompute server-side, then add all items to
     * the cart as a single lucky group.
     */
    public function accept(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => 'required|integer|exists:destinations,id',
            'room_id' => 'required|integer',
            'activity_ids' => 'required|array|min:1|max:5',
            'activity_ids.*' => 'integer',
            'nights' => 'required|integer|min:1|max:7',
            'pax' => 'required|integer|min:1|max:4',
            'max_budget' => 'required|numeric|min:1',
            'budget_exceeded' => 'nullable|boolean',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        try {
            $verified = $this->luckyService->verifyAcceptedItinerary($validated);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $groupId = (string) Str::uuid();

        try {
            $this->cartService->add($request, [
                'item_type' => 'room',
                'item_id' => $verified['room_id'],
                'quantity' => 1,
                'selected_pax' => (int) $validated['pax'],
                'check_in_date' => $validated['check_in_date'],
                'check_out_date' => $validated['check_out_date'],
            ], $groupId);

            foreach ($verified['activity_ids'] as $activityId) {
                $this->cartService->add($request, [
                    'item_type' => 'activity',
                    'item_id' => $activityId,
                    'quantity' => 1,
                    'selected_pax' => (int) $validated['pax'],
                ], $groupId);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your surprise itinerary is in your Trip Basket!',
            'redirect_url' => route('cart.index'),
        ]);
    }
}
