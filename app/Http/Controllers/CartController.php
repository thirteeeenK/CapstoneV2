<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

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
        $totalRooms = (int) ($room->total_rooms ?: ($room->total_number_of_rooms ?: 2));

        $checkIn = $item->check_in_date ? \Carbon\Carbon::parse($item->check_in_date) : null;
        $checkOut = $item->check_out_date ? \Carbon\Carbon::parse($item->check_out_date) : null;

        $bookedCount = 0;
        if ($checkIn && $checkOut) {
            $bookedCount = (int) BookingItem::where('item_type', 'room')
                ->where('item_id', $room->id)
                ->whereHas('booking', fn ($q) => $q->whereIn('status', Booking::HOLD_STATUSES))
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->whereBetween('check_in_date', [$checkIn, $checkOut->copy()->subDay()])
                        ->orWhereBetween('check_out_date', [$checkIn->copy()->addDay(), $checkOut]);
                })
                ->sum('quantity');
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
            'available_count' => $available,
            'available_notice' => $notice,
        ];
    }

    /**
     * Build the "I'm Feeling Lucky" group metadata map for the cart payload.
     */
    protected function buildGroups($items): array
    {
        $groups = [];
        $grouped = $items->filter(fn ($item) => ! empty($item->lucky_group_id))
            ->groupBy('lucky_group_id');

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

            $groups[] = [
                'id' => $groupId,
                'title' => implode(' — ', $titleParts).' Surprise Itinerary',
                'destination_name' => $destinationName,
                'nights' => $nights,
                'item_count' => $groupItems->count(),
                'selected_count' => $selectedItems->count(),
                'is_selected' => $groupItems->count() > 0 && $selectedItems->count() === $groupItems->count(),
                'subtotal' => $subtotal,
                'formatted_subtotal' => '₱'.number_format($subtotal, 2),
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
                    'title' => $item->item_title,
                    'subtitle' => $item->item_subtitle,
                    'hotel_name' => $item->hotel_name,
                    'location_name' => $item->location_name,
                    'date_details' => $item->date_details,
                    'image' => $item->item_image,
                    'unit_rate' => $item->unit_rate,
                    'subtotal' => $item->subtotal,
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
                'quantity' => 'nullable|integer|min:1',
                'selected_pax' => 'nullable|integer|min:1',
                'check_in_date' => 'nullable|date|after_or_equal:today',
                'check_out_date' => 'nullable|date|after:check_in_date',
                'room_option' => 'nullable|string|in:shared,separate',
                'notes' => 'nullable|string',
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
     * Toggle selection for every item in an "I'm Feeling Lucky" group at once.
     */
    public function toggleGroup(Request $request, $groupId)
    {
        $total = $this->getCartQuery($request)->where('lucky_group_id', $groupId)->count();
        if ($total === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Itinerary group not found.',
            ], 404);
        }

        $selected = $this->getCartQuery($request)
            ->where('lucky_group_id', $groupId)
            ->where('is_selected', true)
            ->count();

        $target = $selected !== $total;

        $this->getCartQuery($request)
            ->where('lucky_group_id', $groupId)
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

        if (! $item) {
            $item = CartItem::find($id);
        }

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
                'quantity' => 'nullable|integer|min:1',
                'selected_pax' => 'nullable|integer|min:1',
                'check_in_date' => 'nullable|date|after_or_equal:today',
                'check_out_date' => 'nullable|date|after:check_in_date',
                'is_selected' => 'nullable|boolean',
                'room_option' => 'nullable|string|in:shared,separate',
                'notes' => 'nullable|string',
            ]);

            if (isset($validated['quantity'])) {
                $newQty = (int) $validated['quantity'];
                if ($cartItem->item_type === 'room' && $cartItem->itemable) {
                    $room = $cartItem->itemable;
                    $totalRooms = (int) ($room->total_rooms ?: ($room->total_number_of_rooms ?: 2));

                    $checkIn = $cartItem->check_in_date ? \Carbon\Carbon::parse($cartItem->check_in_date) : null;
                    $checkOut = $cartItem->check_out_date ? \Carbon\Carbon::parse($cartItem->check_out_date) : null;

                    $bookedCount = 0;
                    if ($checkIn && $checkOut) {
                        $bookedCount = BookingItem::where('item_type', 'room')
                            ->where('item_id', $room->id)
                            ->whereHas('booking', fn ($q) => $q->whereIn('status', Booking::HOLD_STATUSES))
                            ->where(function ($q) use ($checkIn, $checkOut) {
                                $q->whereBetween('check_in_date', [$checkIn, $checkOut->copy()->subDay()])
                                    ->orWhereBetween('check_out_date', [$checkIn->copy()->addDay(), $checkOut]);
                            })
                            ->sum('quantity');
                    }

                    $available = max(1, $totalRooms - $bookedCount);
                    $newQty = min($newQty, $available);
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
