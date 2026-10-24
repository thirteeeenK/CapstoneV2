<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\CartItem;
use App\Models\LuckyOffer;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\TripItinerary;
use App\Services\CartService;
use App\Services\RoomAvailabilityService;
use App\Services\TripBookingDatePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService, protected RoomAvailabilityService $roomAvailability) {}

    /**
     * Get or generate cart identifier for session/user.
     */
    protected function getSessionToken(Request $request): string
    {
        return $this->cartService->getSessionToken($request);
    }

    protected function getCartQuery(Request $request)
    {
        return $this->cartService->getCartQuery($request);
    }

    protected function getItemsWithRelations(Request $request)
    {
        return $this->getCartQuery($request)
            ->with([
                'itemable' => function ($morphTo) {
                    $morphTo->morphWith([
                        RoomType::class => ['hotel', 'hotel.destination'],
                        ActivityModel::class => ['destination'],
                        AddOnModel::class => ['destination'],
                        Package::class => ['destination', 'hotels'],
                    ]);
                },
            ])
            ->get();
    }

    /**
     * Display dedicated Cart page.
     */
    public function index(Request $request)
    {
        $cartItems = $this->getItemsWithRelations($request);
        $validItems = $cartItems->filter(fn ($item) => $item->itemable !== null);
        $groups = $this->buildGroups($validItems);

        return view('cart.index', compact('cartItems', 'groups'));
    }

    public function getAvailableRoomInventory(CartItem $item): array
    {
        if ($item->item_type !== 'room' || ! $item->itemable) {
            return [
                'total_rooms' => 10,
                'booked_count' => 0,
                'available_count' => 10,
                'available_notice' => '10 available',
            ];
        }

        $room = $item->itemable;
        $totalRooms = $this->roomAvailability->totalRooms($room);

        $bookedCount = 0;
        $inBasket = 0;
        if ($item->check_in_date && $item->check_out_date) {
            $bookedCount = (int) $this->roomAvailability->check($room, $item->check_in_date->copy()->startOfDay(), $item->check_out_date->copy()->startOfDay())['booked_count'];
            // Other selected lines for this room on overlapping dates draw from the same stock.
            $inBasket = $this->cartService->basketRoomQuantity($item->user_id, $item->session_token, $room->id, $item->check_in_date, $item->check_out_date, $item->id);
        }

        $available = max(0, $totalRooms - $bookedCount);

        if ($bookedCount > 0) {
            $notice = "{$available} of {$totalRooms} rooms available ({$bookedCount} ".($bookedCount === 1 ? 'room is' : 'rooms are').' currently pending admin approval)';
        } else {
            $notice = "{$totalRooms} of {$totalRooms} rooms available in resort";
        }

        return [
            'total_rooms' => $totalRooms,
            'booked_count' => $bookedCount,
            'available_count' => max(0, $available - $inBasket),
            'available_notice' => $notice,
        ];
    }

    /**
     * Build itinerary group metadata for the basket page and drawer.
     */
    protected function buildGroups($items): array
    {
        $groups = [];
        $grouped = $items->filter(fn ($item) => $item->basket_group_id !== null)
            ->groupBy('basket_group_id');
        $itineraries = TripItinerary::query()->whereIn('id', $items->pluck('trip_itinerary_id')->filter()->unique())
            ->select(['id', 'trip_plan_id'])->with('plan:id,title')->get()->keyBy('id');
        $offers = LuckyOffer::query()->whereIn('id', $items->pluck('lucky_offer_id')->filter()->unique())->whereNotNull('code')
            ->get(['id', 'code', 'expires_at', 'terms'])->keyBy('id');

        foreach ($grouped as $groupId => $groupItems) {
            $roomItem = $groupItems->first(fn ($i) => $i->item_type === 'room');
            $destinationName = $groupItems->first()?->location_name;
            $nights = null;

            if ($roomItem && $roomItem->check_in_date && $roomItem->check_out_date) {
                $nights = max(1, Carbon::parse($roomItem->check_in_date)
                    ->diffInDays(Carbon::parse($roomItem->check_out_date)));
            }

            $titleParts = array_filter([$destinationName, $nights ? $nights.'N' : null]);
            $selectedItems = $groupItems->where('is_selected', true);
            $subtotal = $selectedItems->sum(fn ($item) => $item->subtotal);
            $isChat = str_starts_with((string) $groupId, 'ch');
            $isPlanner = str_starts_with((string) $groupId, 'tp-');
            $plannerTitle = $isPlanner ? $itineraries->get($groupItems->first()->trip_itinerary_id)?->plan?->title : null;
            $offer = $offers->get($groupItems->first()->lucky_offer_id);

            $groups[] = [
                'id' => $groupId,
                'title' => $isPlanner ? ($plannerTitle ?: implode(' — ', $titleParts).' Itinerary') : implode(' — ', $titleParts).($isChat ? ' Itinerary' : ' Surprise Itinerary'),
                'label' => $isPlanner ? 'Trip Planner' : ($isChat ? 'Chat Itinerary' : "I'm Feeling Lucky"),
                'icon' => $isPlanner || $isChat ? 'route' : 'casino',
                'destination_name' => $destinationName,
                'nights' => $nights,
                'item_count' => $groupItems->count(),
                'selected_count' => $selectedItems->count(),
                'is_selected' => $groupItems->count() > 0 && $selectedItems->count() === $groupItems->count(),
                'subtotal' => $subtotal,
                'formatted_subtotal' => '₱'.number_format($subtotal, 2),
                'lucky_code' => $offer?->code,
                'lucky_code_label' => $offer ? Promotion::describeDiscount($offer->terms).' · valid until '.$offer->expires_at?->timezone('Asia/Manila')->format('M j, g:i A') : null,
            ];
        }

        return $groups;
    }

    /**
     * Get JSON data for floating drawer & Ajax updates.
     */
    public function data(Request $request)
    {
        $items = $this->getItemsWithRelations($request);

        // Filter out items whose target entity was deleted
        $validItems = $items->filter(fn ($item) => $item->itemable !== null);

        $selectedItems = $validItems->where('is_selected', true);
        $subtotal = $selectedItems->sum(fn ($item) => $item->subtotal);

        return response()->json([
            'success' => true,
            'items' => $validItems->values()->map(function ($item) {
                $inv = $this->getAvailableRoomInventory($item);

                return [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'selected_pax' => $item->selected_pax,
                    'room_option' => $item->room_option,
                    'min_pax' => ($item->item_type === 'package' && $item->itemable) ? (int) $item->itemable->min_pax : null,
                    'check_in_date' => $item->check_in_date ? Carbon::parse($item->check_in_date)->format('Y-m-d') : null,
                    'check_out_date' => $item->check_out_date ? Carbon::parse($item->check_out_date)->format('Y-m-d') : null,
                    'is_selected' => $item->is_selected,
                    'is_expired' => $item->isExpired(),
                    'notes' => $item->notes,
                    'lucky_group_id' => $item->lucky_group_id,
                    'group_id' => $item->basket_group_id,
                    'title' => $item->item_title,
                    'subtitle' => $item->item_subtitle,
                    'hotel_name' => $item->hotel_name,
                    'location_name' => $item->location_name,
                    'date_details' => $item->date_details,
                    'image' => $item->item_image,
                    'unit_rate' => $item->unit_rate,
                    'subtotal' => $item->subtotal,
                    'is_per_person' => $item->itemable && method_exists($item->itemable, 'isPerPersonRate') ? $item->itemable->isPerPersonRate() : true,
                    'formatted_unit_rate' => '₱'.number_format($item->unit_rate, 2),
                    'formatted_subtotal' => '₱'.number_format($item->subtotal, 2),
                    'max_qty' => $inv['available_count'],
                    'total_rooms' => $inv['total_rooms'],
                    'booked_count' => $inv['booked_count'],
                    'available_notice' => $inv['available_notice'],
                ];
            }),
            'groups' => $this->buildGroups($validItems),
            'total_count' => $validItems->sum('quantity'),
            'selected_count' => $selectedItems->count(),
            'subtotal' => $subtotal,
            'formatted_subtotal' => '₱'.number_format($subtotal, 2),
        ]);
    }

    /**
     * Add item to cart.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'item_type' => 'required|string|in:room,activity,addon,package',
                'item_id' => 'required|integer',
                'quantity' => 'nullable|integer|min:1|max:50',
                'selected_pax' => 'nullable|integer|min:1|max:50',
                'check_in_date' => 'nullable|date|after:today',
                'check_out_date' => 'nullable|date|after:check_in_date',
                'room_option' => 'nullable|string|in:shared,separate',
                'notes' => 'nullable|string|max:1000',
            ]);

            // Enforce required check-in and check-out dates for Room items
            if ($validated['item_type'] === 'room') {
                if (empty($validated['check_in_date']) || empty($validated['check_out_date'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please select your stay check-in and check-out dates before adding this room to your Trip Basket.',
                    ], 422);
                }
            }

            $result = $this->cartService->add($request, $validated);
            $cartItem = $result['cart_item'];
            $cartItem->load('itemable');

            if ($result['status'] === 'already_in_cart') {
                return response()->json([
                    'success' => true,
                    'already_in_cart' => true,
                    'message' => '"'.$cartItem->item_title.'" is already in your Trip Basket. Adjust the traveler count from your cart.',
                    'cart_item' => [
                        'id' => $cartItem->id,
                        'title' => $cartItem->item_title,
                        'subtitle' => $cartItem->item_subtitle,
                        'image' => $cartItem->item_image,
                        'quantity' => $cartItem->quantity,
                        'formatted_subtotal' => '₱'.number_format($cartItem->subtotal, 2),
                    ],
                ]);
            }

            if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => true,
                    'message' => 'Item added to your Trip Basket!',
                    'cart_item' => [
                        'id' => $cartItem->id,
                        'title' => $cartItem->item_title,
                        'subtitle' => $cartItem->item_subtitle,
                        'hotel_name' => $cartItem->hotel_name,
                        'location_name' => $cartItem->location_name,
                        'image' => $cartItem->item_image,
                        'quantity' => $cartItem->quantity,
                        'formatted_subtotal' => '₱'.number_format($cartItem->subtotal, 2),
                    ],
                ]);
            }

            return redirect()->back()->with('success', 'Item added to your Trip Basket!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Cart store error: '.$e->getMessage());
            if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add item: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', 'Could not add item to cart.');
        }
    }

    /**
     * Add a whole chat itinerary (room + activities) to the cart as one group.
     */
    public function addItinerary(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'activity_ids' => 'nullable|array',
            'activity_ids.*' => 'integer|exists:activities,id',
            'pax' => 'required|integer|min:1|max:20',
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        // 'ch' + hex-uuid (34 chars) fits lucky_group_id varchar(36); 'h' is not a hex digit, so dashed UUIDs never collide.
        $groupId = 'ch'.str_replace('-', '', (string) Str::uuid());

        try {
            // All or nothing: one unavailable activity must not leave half an itinerary in the basket.
            DB::transaction(function () use ($request, $validated, $groupId): void {
                $this->cartService->add($request, [
                    'item_type' => 'room',
                    'item_id' => $validated['room_id'],
                    'quantity' => 1,
                    'selected_pax' => $validated['pax'],
                    'check_in_date' => $validated['check_in_date'],
                    'check_out_date' => $validated['check_out_date'],
                ], $groupId);

                foreach (array_values(array_unique($validated['activity_ids'] ?? [])) as $activityId) {
                    $this->cartService->add($request, [
                        'item_type' => 'activity',
                        'item_id' => $activityId,
                        'quantity' => 1,
                        'selected_pax' => $validated['pax'],
                    ], $groupId);
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your itinerary is in your Trip Basket!',
            'group_id' => $groupId,
            'cart_count' => $this->getCartQuery($request)->count(),
        ]);
    }

    /**
     * Toggle selection for every item in a basket itinerary group at once.
     */
    public function toggleGroup(Request $request, $groupId)
    {
        $isPlanner = preg_match('/^tp-([1-9][0-9]*)$/', (string) $groupId, $matches) === 1;
        $column = $isPlanner ? 'trip_itinerary_id' : 'lucky_group_id';
        $value = $isPlanner ? $matches[1] : $groupId;
        $total = $this->getCartQuery($request)->where($column, $value)->count();
        if ($total === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Itinerary group not found.',
            ], 404);
        }

        $selected = $this->getCartQuery($request)
            ->where($column, $value)
            ->where('is_selected', true)
            ->count();

        $target = $selected !== $total;

        $this->getCartQuery($request)
            ->where($column, $value)
            ->update(['is_selected' => $target]);

        return $this->data($request);
    }

    /**
     * Toggle or set selection state for all items in the cart at once.
     */
    public function toggleAll(Request $request)
    {
        $query = $this->getCartQuery($request);
        $total = $query->count();
        if ($total > 0) {
            if ($request->has('is_selected')) {
                $target = filter_var($request->input('is_selected'), FILTER_VALIDATE_BOOLEAN);
            } else {
                $selected = $this->getCartQuery($request)->where('is_selected', true)->count();
                $target = $selected !== $total;
            }

            $this->getCartQuery($request)->update(['is_selected' => $target]);
        }

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json' || $request->expectsJson()) {
            return $this->data($request);
        }

        return redirect()->back();
    }

    protected function findCartItem(Request $request, $id): ?CartItem
    {
        $userId = auth()->id();
        $sessionToken = $this->getSessionToken($request);

        $item = CartItem::where('id', $id)
            ->where(function ($q) use ($userId, $sessionToken) {
                if ($userId) {
                    $q->where('user_id', $userId)->orWhere('session_token', $sessionToken);
                } else {
                    $q->where('session_token', $sessionToken);
                }
            })
            ->first();

        if ($item && $userId && ! $item->user_id) {
            $item->user_id = $userId;
            $item->save();
        }

        return $item;
    }

    /**
     * Update item quantity, pax, dates, or selection state.
     */
    public function update(Request $request, $id)
    {
        try {
            $cartItem = $this->findCartItem($request, $id);
            if (! $cartItem) {
                return response()->json(['success' => false, 'message' => 'Cart item not found.'], 404);
            }

            $validated = $request->validate([
                'quantity' => 'nullable|integer|min:1|max:50',
                'selected_pax' => 'nullable|integer|min:1|max:50',
                'check_in_date' => 'nullable|date|after:today',
                'check_out_date' => 'nullable|date|after:check_in_date',
                'is_selected' => 'nullable|boolean',
                'room_option' => 'nullable|string|in:shared,separate',
                'notes' => 'nullable|string|max:1000',
            ]);
            if ($cartItem->item_type === 'room' && array_key_exists('check_in_date', $validated)) {
                app(TripBookingDatePolicy::class)->assertBookableArrival($validated['check_in_date'], 'check_in_date');
            }

            if (isset($validated['quantity'])) {
                $newQty = (int) $validated['quantity'];
                if ($cartItem->item_type === 'room' && $cartItem->itemable) {
                    $newQty = min($newQty, max(1, $this->getAvailableRoomInventory($cartItem)['available_count']));
                }
                if ($cartItem->item_type === 'package') {
                    $newQty = max(1, $newQty);
                }
                $cartItem->quantity = $newQty;
                if (in_array($cartItem->item_type, ['activity', 'addon', 'package'])) {
                    $cartItem->selected_pax = $newQty;
                }
            }
            if (isset($validated['selected_pax'])) {
                $newPax = (int) $validated['selected_pax'];
                if ($cartItem->item_type === 'package') {
                    $newPax = max(1, $newPax);
                }
                $cartItem->selected_pax = $newPax;
                if (in_array($cartItem->item_type, ['activity', 'addon', 'package'])) {
                    $cartItem->quantity = $newPax;
                }
            }
            if (array_key_exists('check_in_date', $validated)) {
                $cartItem->check_in_date = $validated['check_in_date'];
            }
            if (array_key_exists('check_out_date', $validated)) {
                $cartItem->check_out_date = $validated['check_out_date'];
            }
            if (isset($validated['is_selected'])) {
                $cartItem->is_selected = filter_var($validated['is_selected'], FILTER_VALIDATE_BOOLEAN);
            }
            if (array_key_exists('notes', $validated)) {
                $cartItem->notes = $validated['notes'];
            }
            // Package-only room preference (no price effect).
            if (array_key_exists('room_option', $validated) && $cartItem->item_type === 'package') {
                $cartItem->room_option = $validated['room_option'];
            }

            $cartItem->save();

            if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json' || $request->expectsJson()) {
                return $this->data($request);
            }

            return redirect()->back()->with('success', 'Cart updated.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Cart update error: '.$e->getMessage());
            if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json' || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', 'Could not update cart.');
        }
    }

    /**
     * Toggle selection checkbox.
     */
    public function toggleSelect(Request $request, $id)
    {
        $cartItem = $this->findCartItem($request, $id);
        if ($cartItem) {
            $cartItem->is_selected = ! $cartItem->is_selected;
            $cartItem->save();
        }

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json' || $request->expectsJson()) {
            return $this->data($request);
        }

        return redirect()->back();
    }

    /**
     * Remove item from cart.
     */
    public function destroy(Request $request, $id)
    {
        $cartItem = $this->findCartItem($request, $id);
        if ($cartItem) {
            $cartItem->delete();
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart.',
            ]);
        }

        return redirect()->back()->with('success', 'Item removed.');
    }

    /**
     * Clear all items in cart.
     */
    public function clear(Request $request)
    {
        $this->getCartQuery($request)->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Cart cleared.',
            ]);
        }

        return redirect()->back()->with('success', 'Cart cleared.');
    }
}
