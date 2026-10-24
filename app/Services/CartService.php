<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\CartItem;
use App\Models\Package;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartService
{
    public function __construct(private RoomAvailabilityService $availability) {}

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
        $listing = $this->bookableListing($itemType, $itemId);
        if (! $listing) {
            throw new \RuntimeException('This listing is no longer available. Please choose another option.');
        }
        if ($itemType === 'room') {
            app(TripBookingDatePolicy::class)->assertBookableArrival($checkIn, 'check_in_date');
        }
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
        // Rooms already in the basket for overlapping dates count too, so repeat adds cannot exceed stock.
        if ($itemType === 'room' && $checkIn && $checkOut) {
            $remaining = $this->availability->check($listing, Carbon::parse($checkIn)->startOfDay(), Carbon::parse($checkOut)->startOfDay())['remaining'];
            $inBasket = $this->basketRoomQuantity($userId, $sessionToken, $itemId, $checkIn, $checkOut);
            if ($remaining < $quantity + $inBasket) {
                throw new \RuntimeException($inBasket > 0
                    ? 'Only '.$remaining.' of this room '.($remaining === 1 ? 'is' : 'are').' left for your dates, and your Trip Basket already holds '.$inBasket.'.'
                    : 'Sorry, this room is sold out for your selected dates.');
            }
        }

        // Check if item already exists in cart with same type, id, and dates
        $query = CartItem::where('item_type', $itemType)
            ->where('item_id', $itemId)->whereNull('trip_itinerary_id');

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

        // Lock the matching line so two quick adds cannot both read the old quantity.
        return DB::transaction(function () use ($query, $luckyGroupId, $quantity, $hasPax, $selectedPax, $userId, $sessionToken, $itemType, $itemId, $roomOption, $checkIn, $checkOut, $payload): array {
            $existingItem = $luckyGroupId ? null : $query->lockForUpdate()->first();

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
        });
    }

    /**
     * Rooms of one type that the basket's selected lines already claim for
     * dates overlapping the given stay, optionally ignoring one line.
     */
    public function basketRoomQuantity(?int $userId, ?string $sessionToken, int $roomId, mixed $checkIn, mixed $checkOut, ?int $exceptLineId = null): int
    {
        return (int) CartItem::query()->where('item_type', 'room')->where('item_id', $roomId)->where('is_selected', true)
            ->when($userId, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->where('session_token', $sessionToken))
            ->where('check_in_date', '<', Carbon::parse($checkOut)->toDateString())
            ->where('check_out_date', '>', Carbon::parse($checkIn)->toDateString())
            ->when($exceptLineId, fn ($q) => $q->whereKeyNot($exceptLineId))
            ->sum('quantity');
    }

    /**
     * The listing behind a basket line, or null once it is deleted or hidden.
     * A room also needs its hotel to be shown.
     */
    public function bookableListing(string $itemType, int $itemId): ?Model
    {
        return match ($itemType) {
            'room' => RoomType::query()->where('is_shown', true)->whereHas('hotel', fn ($q) => $q->where('is_shown', true))->find($itemId),
            'activity' => ActivityModel::query()->where('is_shown', true)->find($itemId),
            'addon' => AddOnModel::query()->where('is_shown', true)->find($itemId),
            'package' => Package::query()->where('is_active', true)->find($itemId),
            default => null,
        };
    }
}
