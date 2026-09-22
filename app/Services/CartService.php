<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Get or generate cart identifier for session/user.
     */
    public function getSessionToken(Request $request): string
    {
        if ($request->hasSession()) {
            $token = $request->session()->get('cart_session_token');
            if (! $token) {
                $token = (string) Str::uuid();
                $request->session()->put('cart_session_token', $token);
            }

            return $token;
        }

        return 'guest_'.md5($request->ip().($request->header('User-Agent') ?? 'ua'));
    }

    /**
     * Get the cart query scoped to the current user or session.
     */
    public function getCartQuery(Request $request)
    {
        $sessionToken = $this->getSessionToken($request);

        if (auth()->check()) {
            $userId = auth()->id();

            // Automatically claim any guest cart items from the current session
            CartItem::whereNull('user_id')
                ->where('session_token', $sessionToken)
                ->update(['user_id' => $userId]);

            return CartItem::where('user_id', $userId)->orderBy('id');
        }

        return CartItem::where('session_token', $sessionToken)->orderBy('id');
    }

    /**
     * Add an item to the cart (dedupe, room-hold check, pax normalization).
     *
     * @param  array  $payload  item_type, item_id, quantity, selected_pax, check_in_date, check_out_date, notes, room_option
     * @param  string|null  $luckyGroupId  shared group id for "I'm Feeling Lucky" itineraries
     * @return array{status: string, cart_item: CartItem} status: added|incremented|already_in_cart
     *
     * @throws \RuntimeException when the room is sold out for the requested dates
     */
    public function add(Request $request, array $payload, ?string $luckyGroupId = null): array
    {
        $userId = auth()->id();
        $sessionToken = $this->getSessionToken($request);

        $itemType = $payload['item_type'];
        $itemId = (int) $payload['item_id'];
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $hasPax = array_key_exists('selected_pax', $payload) && $payload['selected_pax'] !== null;
        $selectedPax = max(1, (int) ($payload['selected_pax'] ?? 1));
        $checkIn = $payload['check_in_date'] ?? null;
        $checkOut = $payload['check_out_date'] ?? null;
        // Package-only room preference (no price effect). Null = legacy/no choice.
        $roomOption = $itemType === 'package' && in_array($payload['room_option'] ?? null, ['shared', 'separate'], true)
            ? $payload['room_option']
            : null;

        // Past-date guard (Option A: check_in < today is expired)
        if ($itemType === 'room' && $checkIn) {
            if (Carbon::parse($checkIn)->lt(Carbon::today())) {
                throw new \RuntimeException('The selected check-in date has already passed. Please choose a future date.');
            }
        }

        // Room Availability Check before adding to cart
        if ($itemType === 'room' && $checkIn && $checkOut) {
            $room = RoomType::find($itemId);
            if ($room) {
                $checkInDate = Carbon::parse($checkIn);
                $checkOutDate = Carbon::parse($checkOut);

                $bookedCount = BookingItem::where('item_type', 'room')
                    ->where('item_id', $room->id)
                    ->whereHas('booking', fn ($q) => $q->whereIn('status', Booking::HOLD_STATUSES))
                    ->where(function ($q) use ($checkInDate, $checkOutDate) {
                        $q->whereBetween('check_in_date', [$checkInDate, $checkOutDate->copy()->subDay()])
                            ->orWhereBetween('check_out_date', [$checkInDate->copy()->addDay(), $checkOutDate]);
                    })
                    ->sum('quantity');

                $totalRooms = max(1, (int) ($room->total_rooms ?: ($room->total_number_of_rooms ?: 2)));
                if (($totalRooms - $bookedCount) < $quantity) {
                    throw new \RuntimeException('Sorry, this room is sold out for your selected dates.');
                }
            }
        }

        // Check if item already exists in cart with same type, id, and dates
        $query = CartItem::where('item_type', $itemType)
            ->where('item_id', $itemId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_token', $sessionToken);
        }

        if ($checkIn) {
            $query->where('check_in_date', $checkIn);
        }

        if ($itemType === 'room' && $checkOut) {
            $query->where('check_out_date', $checkOut);
        }

        if ($itemType === 'room' && $hasPax) {
            $query->where('selected_pax', $selectedPax);
        }

        if ($itemType === 'package') {
            // Share vs separate rooms are distinct cart lines.
            $roomOption ? $query->where('room_option', $roomOption) : $query->whereNull('room_option');
        }

        if (in_array($itemType, ['activity', 'addon'])) {
            $paxVal = max($quantity, $selectedPax);
            $quantity = $paxVal;
            $selectedPax = $paxVal;
        } elseif ($itemType === 'package') {
            // min_pax only gates booking eligibility at checkout, never inflates pax
            $paxVal = max(1, $quantity, $selectedPax);
            $quantity = $paxVal;
            $selectedPax = $paxVal;
        }

        $existingItem = $luckyGroupId ? null : $query->first();

        if ($existingItem) {
            // Packages are non-stackable — each package is a single booking entry.
            if ($existingItem->item_type === 'package') {
                $existingItem->load('itemable');

                return ['status' => 'already_in_cart', 'cart_item' => $existingItem];
            }

            $existingItem->quantity += $quantity;
            if ($hasPax) {
                $existingItem->selected_pax = $selectedPax;
            }
            if (in_array($existingItem->item_type, ['activity', 'addon'])) {
                $existingItem->selected_pax = max($existingItem->quantity, $existingItem->selected_pax);
                $existingItem->quantity = $existingItem->selected_pax;
            }
            if ($luckyGroupId) {
                $existingItem->lucky_group_id = $luckyGroupId;
            }
            $existingItem->save();

            return ['status' => 'incremented', 'cart_item' => $existingItem];
        }

        $cartItem = CartItem::create([
            'user_id' => $userId,
            'session_token' => $sessionToken,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'quantity' => $quantity,
            'selected_pax' => $selectedPax,
            'room_option' => $roomOption,
            'check_in_date' => $checkIn ?: null,
            'check_out_date' => $checkOut ?: null,
            'notes' => $payload['notes'] ?? null,
            'is_selected' => true,
            'lucky_group_id' => $luckyGroupId,
        ]);

        return ['status' => 'added', 'cart_item' => $cartItem];
    }
}
