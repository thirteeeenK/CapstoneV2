<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\LuckyOffer;
use App\Services\CartService;
use App\Services\LuckyItineraryService;
use App\Services\LuckyOfferService;
use App\Services\TripBookingDatePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LuckyItineraryController extends Controller
{
    public function __construct(
        protected LuckyItineraryService $luckyService,
        protected CartService $cartService,
        protected LuckyOfferService $offers,
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
     * Generate a random surprise itinerary within the applied filters and save
     * it as the traveler's offer (with a code when a Lucky promotion applies).
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
            'start_date' => 'nullable|date|after:today',
        ]);

        $result = $this->luckyService->generate($validated);

        if (isset($result['message']) && ! isset($result['destination'])) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        $offer = $this->offers->issue($request->user(), $result, (float) ($validated['max_budget'] ?? 20000));
        $result['offer'] = $this->offers->present($offer);

        return response()->json(['success' => true, 'itinerary' => $result]);
    }

    /**
     * Accept a generated itinerary: recompute server-side, then add all items to
     * the cart as a single lucky group. A saved offer is accepted by its id, at
     * most once; the older field-based payload stays supported without a code.
     */
    public function accept(Request $request)
    {
        $validated = $request->validate([
            'offer_id' => 'nullable|uuid',
            'destination_id' => 'required_without:offer_id|integer|exists:destinations,id',
            'room_id' => 'required_without:offer_id|integer',
            'activity_ids' => 'required_without:offer_id|array|min:1|max:5',
            'activity_ids.*' => 'integer',
            'nights' => 'required_without:offer_id|integer|min:1|max:7',
            'pax' => 'required_without:offer_id|integer|min:1|max:4',
            'max_budget' => 'required_without:offer_id|numeric|min:1',
            'budget_exceeded' => 'nullable|boolean',
            'check_in_date' => 'required_without:offer_id|date|after:today',
            'check_out_date' => 'required_without:offer_id|date|after:check_in_date',
        ]);

        $offer = null;
        if (! empty($validated['offer_id'])) {
            $offer = $this->offers->owned($request->user(), $validated['offer_id']);
            if (! $offer) {
                return response()->json(['success' => false, 'message' => 'This Lucky itinerary is not available. Please shuffle for a new one.'], 404);
            }
            if ($offer->accepted_at && CartItem::query()->where('lucky_offer_id', $offer->id)->exists()) {
                return $this->accepted($request, $offer->lucky_group_id, $offer, 'This Lucky itinerary is already in your Trip Basket.');
            }
            if ($offer->isExpired()) {
                return response()->json(['success' => false, 'message' => 'This Lucky itinerary expired. Shuffle for a fresh one at current prices.'], 422);
            }
            $validated = [
                'destination_id' => $offer->destination_id, 'room_id' => $offer->room_id, 'activity_ids' => $offer->activity_ids,
                'nights' => $offer->nights, 'pax' => $offer->pax, 'max_budget' => (float) $offer->max_budget, 'budget_exceeded' => $offer->budget_exceeded,
                'check_in_date' => $offer->check_in_date->toDateString(), 'check_out_date' => $offer->check_out_date->toDateString(),
            ];
        }

        app(TripBookingDatePolicy::class)->assertBookableArrival($validated['check_in_date'], 'check_in_date');

        try {
            $verified = $this->luckyService->verifyAcceptedItinerary($validated);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if ($offer && (int) round($verified['total'] * 100) !== $offer->quoted_total_cents) {
            return response()->json(['success' => false, 'message' => 'Prices changed since this itinerary was generated (now ₱'.number_format($verified['total'], 2).'). Shuffle again for an updated quote.'], 422);
        }

        $groupId = (string) Str::uuid();

        try {
            // All or nothing: one unavailable activity must not leave half an itinerary in the basket.
            $groupId = DB::transaction(function () use ($request, $validated, $verified, $groupId, $offer): string {
                if ($offer) {
                    $locked = LuckyOffer::query()->lockForUpdate()->findOrFail($offer->id);
                    if ($locked->accepted_at && CartItem::query()->where('lucky_offer_id', $locked->id)->exists()) {
                        return $locked->lucky_group_id;
                    }
                }
                $this->cartService->add($request, [
                    'item_type' => 'room',
                    'item_id' => $verified['room_id'],
                    'quantity' => 1,
                    'selected_pax' => (int) $validated['pax'],
                    'check_in_date' => $validated['check_in_date'],
                    'check_out_date' => $validated['check_out_date'],
                ], $groupId, $offer?->id);

                foreach ($verified['activity_ids'] as $activityId) {
                    $this->cartService->add($request, [
                        'item_type' => 'activity',
                        'item_id' => $activityId,
                        'quantity' => 1,
                        'selected_pax' => (int) $validated['pax'],
                    ], $groupId, $offer?->id);
                }
                $offer?->forceFill(['accepted_at' => now(), 'lucky_group_id' => $groupId])->save();

                return $groupId;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->accepted($request, $groupId, $offer, 'Your surprise itinerary is in your Trip Basket!');
    }

    /**
     * Open a saved Lucky itinerary in the Trip Planner to build a checked
     * schedule. The draft-versus-checked status stays with the planner.
     */
    public function plan(Request $request): JsonResponse
    {
        $validated = $request->validate(['offer_id' => 'required|uuid']);
        $offer = $this->offers->owned($request->user(), $validated['offer_id']);
        if (! $offer) {
            return response()->json(['success' => false, 'message' => 'This Lucky itinerary is not available. Please shuffle for a new one.'], 404);
        }
        $plan = $this->offers->planFor($request->user(), $offer);

        return response()->json(['success' => true, 'plan_id' => $plan->id, 'url' => route('trip-planner.index', ['plan' => $plan->id]),
            'message' => 'Opened in the Trip Planner. Build the checked schedule there to see routes and weather; your basket and code are unchanged.']);
    }

    private function accepted(Request $request, ?string $groupId, ?LuckyOffer $offer, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message.($offer?->code ? ' Use code '.$offer->code.' at checkout.' : ''),
            'group_id' => $groupId,
            'offer' => $offer ? $this->offers->present($offer->refresh()) : null,
            'cart_count' => $this->cartService->getCartQuery($request)->count(),
            'cart_total' => round($this->cartService->getCartQuery($request)->get()->sum(fn ($item) => $item->subtotal), 2),
        ]);
    }
}
