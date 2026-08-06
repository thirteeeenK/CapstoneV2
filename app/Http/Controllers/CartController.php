<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CartController extends Controller
{
    /**
     * Get or generate cart identifier for session/user.
     */
    protected function getSessionToken(Request $request): string
    {
        if ($request->hasSession()) {
            $token = $request->session()->get('cart_session_token');
            if (!$token) {
                $token = (string) Str::uuid();
                $request->session()->put('cart_session_token', $token);
            }
            return $token;
        }

        return 'guest_' . md5($request->ip() . ($request->header('User-Agent') ?? 'ua'));
    }

    protected function getCartQuery(Request $request)
    {
        $sessionToken = $this->getSessionToken($request);

        if (auth()->check()) {
            $userId = auth()->id();

            // Automatically claim any guest cart items from the current session
            CartItem::whereNull('user_id')
                ->where('session_token', $sessionToken)
                ->update(['user_id' => $userId]);

            return CartItem::where('user_id', $userId);
        }

        return CartItem::where('session_token', $sessionToken);
    }

    protected function getItemsWithRelations(Request $request)
    {
        return $this->getCartQuery($request)
            ->with([
                'itemable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\RoomType::class => ['hotel', 'hotel.destination'],
                        \App\Models\ActivityModel::class => ['destination'],
                        \App\Models\AddOnModel::class => ['destination'],
                        \App\Models\Package::class => ['destination', 'hotels'],
                    ]);
                }
            ])
            ->get();
    }

    /**
     * Display dedicated Cart page.
     */
    public function index(Request $request)
    {
        $cartItems = $this->getItemsWithRelations($request);
        return view('cart.index', compact('cartItems'));
    }

    public function getAvailableRoomInventory(CartItem $item): array
    {
        if ($item->item_type !== 'room' || !$item->itemable) {
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
            $bookedCount = (int) \App\Models\BookingItem::where('item_type', 'room')
                ->where('item_id', $room->id)
                ->whereHas('booking', fn($q) => $q->whereIn('status', \App\Models\Booking::HOLD_STATUSES))
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->whereBetween('check_in_date', [$checkIn, $checkOut->copy()->subDay()])
                      ->orWhereBetween('check_out_date', [$checkIn->copy()->addDay(), $checkOut]);
                })
                ->sum('quantity');
        }

        $available = max(0, $totalRooms - $bookedCount);

        if ($bookedCount > 0) {
            $notice = "{$available} of {$totalRooms} rooms available ({$bookedCount} " . ($bookedCount === 1 ? 'room is' : 'rooms are') . " currently pending admin approval)";
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
     * Get JSON data for floating drawer & Ajax updates.
     */
    public function data(Request $request)
    {
        $items = $this->getItemsWithRelations($request);

        // Filter out items whose target entity was deleted
        $validItems = $items->filter(fn($item) => $item->itemable !== null);

        $selectedItems = $validItems->where('is_selected', true);
        $subtotal = $selectedItems->sum(fn($item) => $item->subtotal);

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
                    'check_in_date' => $item->check_in_date ? Carbon::parse($item->check_in_date)->format('Y-m-d') : null,
                    'check_out_date' => $item->check_out_date ? Carbon::parse($item->check_out_date)->format('Y-m-d') : null,
                    'is_selected' => $item->is_selected,
                    'notes' => $item->notes,
                    'title' => $item->item_title,
                    'subtitle' => $item->item_subtitle,
                    'hotel_name' => $item->hotel_name,
                    'location_name' => $item->location_name,
                    'date_details' => $item->date_details,
                    'image' => $item->item_image,
                    'unit_rate' => $item->unit_rate,
                    'subtotal' => $item->subtotal,
                    'formatted_unit_rate' => '₱' . number_format($item->unit_rate, 2),
                    'formatted_subtotal' => '₱' . number_format($item->subtotal, 2),
                    'max_qty' => $inv['available_count'],
                    'total_rooms' => $inv['total_rooms'],
                    'booked_count' => $inv['booked_count'],
                    'available_notice' => $inv['available_notice'],
                ];
            }),
            'total_count' => $validItems->sum('quantity'),
            'selected_count' => $selectedItems->count(),
            'subtotal' => $subtotal,
            'formatted_subtotal' => '₱' . number_format($subtotal, 2),
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
                'check_in_date' => 'nullable|date',
                'check_out_date' => 'nullable|date|after_or_equal:check_in_date',
                'notes' => 'nullable|string',
            ]);

            $userId = auth()->id();
            $sessionToken = $this->getSessionToken($request);

            // Enforce required check-in and check-out dates for Room items
            if ($validated['item_type'] === 'room') {
                if (empty($validated['check_in_date']) || empty($validated['check_out_date'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please select your stay check-in and check-out dates before adding this room to your Trip Basket.',
                    ], 422);
                }
            }

            // Room Availability Check before adding to cart
            if ($validated['item_type'] === 'room' && !empty($validated['check_in_date']) && !empty($validated['check_out_date'])) {
                $room = \App\Models\RoomType::find($validated['item_id']);
                if ($room) {
                    $checkIn = \Carbon\Carbon::parse($validated['check_in_date']);
                    $checkOut = \Carbon\Carbon::parse($validated['check_out_date']);

                    $bookedCount = \App\Models\BookingItem::where('item_type', 'room')
                        ->where('item_id', $room->id)
                        ->whereHas('booking', fn($q) => $q->whereIn('status', \App\Models\Booking::HOLD_STATUSES))
                        ->where(function ($q) use ($checkIn, $checkOut) {
                            $q->whereBetween('check_in_date', [$checkIn, $checkOut->copy()->subDay()])
                              ->orWhereBetween('check_out_date', [$checkIn->copy()->addDay(), $checkOut]);
                        })
                        ->sum('quantity');

                    $totalRooms = max(1, (int) ($room->total_rooms ?: ($room->total_number_of_rooms ?: 2)));
                    if (($totalRooms - $bookedCount) < ($validated['quantity'] ?? 1)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Sorry, this room is sold out for your selected dates.',
                        ], 422);
                    }
                }
            }

            // Check if item already exists in cart with same type, id, and dates
            $query = CartItem::where('item_type', $validated['item_type'])
                ->where('item_id', $validated['item_id']);

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_token', $sessionToken);
            }

            if (!empty($validated['check_in_date'])) {
                $query->where('check_in_date', $validated['check_in_date']);
            }

            $existingItem = $query->first();

            if ($existingItem) {
                $existingItem->quantity += ($validated['quantity'] ?? 1);
                if (!empty($validated['selected_pax'])) {
                    $existingItem->selected_pax = $validated['selected_pax'];
                }
                $existingItem->save();
                $cartItem = $existingItem;
            } else {
                $cartItem = CartItem::create([
                    'user_id' => $userId,
                    'session_token' => $sessionToken,
                    'item_type' => $validated['item_type'],
                    'item_id' => $validated['item_id'],
                    'quantity' => $validated['quantity'] ?? 1,
                    'selected_pax' => $validated['selected_pax'] ?? 1,
                    'check_in_date' => !empty($validated['check_in_date']) ? $validated['check_in_date'] : null,
                    'check_out_date' => !empty($validated['check_out_date']) ? $validated['check_out_date'] : null,
                    'notes' => $validated['notes'] ?? null,
                    'is_selected' => true,
                ]);
            }

            $cartItem->load('itemable');

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
                        'formatted_subtotal' => '₱' . number_format($cartItem->subtotal, 2),
                    ],
                ]);
            }

            return redirect()->back()->with('success', 'Item added to your Trip Basket!');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Cart store error: ' . $e->getMessage());
            if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add item: ' . $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->with('error', 'Could not add item to cart.');
        }
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

        if (!$item) {
            $item = CartItem::find($id);
        }

        if ($item && $userId && !$item->user_id) {
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
            if (!$cartItem) {
                return response()->json(['success' => false, 'message' => 'Cart item not found.'], 404);
            }

            $validated = $request->validate([
                'quantity' => 'nullable|integer|min:1',
                'selected_pax' => 'nullable|integer|min:1',
                'check_in_date' => 'nullable|date',
                'check_out_date' => 'nullable|date',
                'is_selected' => 'nullable|boolean',
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
                        $bookedCount = \App\Models\BookingItem::where('item_type', 'room')
                            ->where('item_id', $room->id)
                            ->whereHas('booking', fn($q) => $q->whereIn('status', \App\Models\Booking::HOLD_STATUSES))
                            ->where(function ($q) use ($checkIn, $checkOut) {
                                $q->whereBetween('check_in_date', [$checkIn, $checkOut->copy()->subDay()])
                                  ->orWhereBetween('check_out_date', [$checkIn->copy()->addDay(), $checkOut]);
                            })
                            ->sum('quantity');
                    }

                    $available = max(1, $totalRooms - $bookedCount);
                    $newQty = min($newQty, $available);
                }
                $cartItem->quantity = $newQty;
            }
            if (isset($validated['selected_pax'])) {
                $cartItem->selected_pax = $validated['selected_pax'];
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

            $cartItem->save();

            if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json' || $request->expectsJson()) {
                return $this->data($request);
            }

            return redirect()->back()->with('success', 'Cart updated.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Cart update error: ' . $e->getMessage());
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
            $cartItem->is_selected = !$cartItem->is_selected;
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
