<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\PassengerCategoryRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingRequestService
{
    /**
     * Get the active (selected) cart query for the user/session.
     */
    public function getCartQuery(Request $request)
    {
        $sessionToken = $request->hasSession() ? $request->session()->get('cart_session_token') : null;
        if (!$sessionToken && $request->hasSession()) {
            $sessionToken = (string) Str::uuid();
            $request->session()->put('cart_session_token', $sessionToken);
        }
        if (!$sessionToken) {
            $sessionToken = 'guest_' . md5($request->ip() . ($request->header('User-Agent') ?? 'ua'));
        }

        if (auth()->check()) {
            $userId = auth()->id();

            // Automatically claim any guest cart items from the current session
            CartItem::whereNull('user_id')
                ->where('session_token', $sessionToken)
                ->update(['user_id' => $userId]);

            return CartItem::where('user_id', $userId)->where('is_selected', true);
        }

        return CartItem::where('session_token', $sessionToken)->where('is_selected', true);
    }

    /**
     * Build a pending Booking (and snapshot BookingItems) from the selected cart items,
     * then clear the processed cart items.
     *
     * @throws \RuntimeException when the cart is empty
     */
    public function buildFromCart(Request $request, array $validated): Booking
    {
        $cartItems = $this->getCartQuery($request)->with(['itemable'])->get();

        if ($cartItems->isEmpty()) {
            throw new \RuntimeException('Your Trip Basket is empty. Cannot process booking.');
        }

        $rulesMap = PassengerCategoryRule::getActiveRulesMap();

        $guestManifest = [];
        $discountAmount = 0.00;
        $surchargeAmount = 0.00;

        if (!empty($validated['guest_manifest'])) {
            $decoded = json_decode($validated['guest_manifest'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $g) {
                    if (!empty($g['full_name'])) {
                        $guestManifest[] = $g;
                        $cat = $g['category'] ?? 'Adult';

                        if (isset($rulesMap[$cat])) {
                            $rule = $rulesMap[$cat];
                            if ($rule->adjustment_type === 'discount') {
                                $discountAmount += (float) $rule->amount;
                            } elseif ($rule->adjustment_type === 'surcharge') {
                                $surchargeAmount += (float) $rule->amount;
                            }
                        }
                    }
                }
            }
        }

        // Process any itemized manifests (room, package, activity, addon)
        foreach ($request->all() as $reqKey => $reqVal) {
            if (str_starts_with($reqKey, 'package_manifest_') || str_starts_with($reqKey, 'room_manifest_')) {
                if (is_string($reqVal) && !empty($reqVal)) {
                    $decoded = json_decode($reqVal, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $g) {
                            if (!empty($g['full_name'])) {
                                $guestManifest[] = $g;
                                $cat = $g['category'] ?? 'Adult';

                                if (isset($rulesMap[$cat])) {
                                    $rule = $rulesMap[$cat];
                                    if ($rule->adjustment_type === 'discount') {
                                        $discountAmount += (float) $rule->amount;
                                    } elseif ($rule->adjustment_type === 'surcharge') {
                                        $surchargeAmount += (float) $rule->amount;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $bookingCode = $this->generateBookingCode();
        $manifestCount = count($guestManifest);
        $totalAmount = 0.00;
        $calculatedItemSubtotals = [];

        foreach ($cartItems as $item) {
            $itemSubtotal = $item->subtotal;
            $effectivePax = max(1, (int) $item->selected_pax);

            if ($item->item_type === 'room' && $item->itemable && method_exists($item->itemable, 'calculateNightlyRate')) {
                $effectivePax = max($effectivePax, $manifestCount);
                $nights = ($item->check_in_date && $item->check_out_date)
                    ? max(1, (int) (strtotime($item->check_out_date) - strtotime($item->check_in_date)) / 86400)
                    : 1;
                $unitRate = $item->itemable->calculateNightlyRate($effectivePax);
                $itemSubtotal = $unitRate * $nights * max(1, $item->quantity);
            }

            $calculatedItemSubtotals[$item->id] = [
                'subtotal' => $itemSubtotal,
                'pax' => $effectivePax,
            ];
            $totalAmount += $itemSubtotal;
        }

        $netAmount = max(0.00, $totalAmount - $discountAmount + $surchargeAmount);

        return DB::transaction(function () use ($request, $validated, $cartItems, $calculatedItemSubtotals, $bookingCode, $guestManifest, $totalAmount, $discountAmount, $surchargeAmount, $netAmount) {
            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'user_id' => auth()->id(),
                'status' => Booking::STATUS_PENDING,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $surchargeAmount,
                'net_amount' => $netAmount,
                'payment_status' => Booking::PAYMENT_UNPAID,
                'contact_name' => $validated['contact_name'],
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
                'special_requests' => $validated['special_requests'] ?? null,
                'guest_manifest' => $guestManifest,
            ]);

            foreach ($cartItems as $item) {
                $calc = $calculatedItemSubtotals[$item->id] ?? ['subtotal' => $item->subtotal, 'pax' => $item->selected_pax];
                $finalSubtotal = $calc['subtotal'];
                $finalPax = $calc['pax'];

                BookingItem::create([
                    'booking_id' => $booking->id,
                    'item_type' => $item->item_type,
                    'item_id' => $item->item_id,
                    'item_title' => $item->item_title,
                    'item_subtitle' => $item->item_subtitle,
                    'hotel_name' => $item->hotel_name,
                    'unit_price' => $finalSubtotal / max(1, $item->quantity),
                    'quantity' => $item->quantity,
                    'selected_pax' => $finalPax,
                    'check_in_date' => $item->check_in_date,
                    'check_out_date' => $item->check_out_date,
                    'nights' => $item->check_in_date && $item->check_out_date
                        ? max(1, (int) (strtotime($item->check_out_date) - strtotime($item->check_in_date)) / 86400)
                        : 1,
                    'subtotal' => $finalSubtotal,
                    'item_snapshot' => [
                        'title' => $item->item_title,
                        'subtitle' => $item->item_subtitle,
                        'hotel_name' => $item->hotel_name,
                        'location_name' => $item->location_name,
                        'image' => $item->item_image,
                    ],
                ]);
            }

            CartItem::whereIn('id', $cartItems->pluck('id')->toArray())->delete();

            return $booking;
        });
    }

    /**
     * Apply admin availability adjustments to booking items and recompute totals.
     *
     * @param  array  $adjustments  items[id => ['include' => bool, 'quantity' => int, 'admin_note' => ?string]]
     * @return array{total: float, net: float, included: int, excluded: int}
     */
    public function repriceForApproval(Booking $booking, array $adjustments): array
    {
        $total = 0.00;
        $included = 0;
        $excluded = 0;

        foreach ($booking->items as $item) {
            $adj = $adjustments[$item->id] ?? null;

            if ($adj && !empty($adj['include'])) {
                $quantity = max(1, (int) ($adj['quantity'] ?? $item->quantity));
                $item->quantity = $quantity;
                $item->subtotal = round((float) $item->unit_price * $quantity, 2);
                $item->availability_status = BookingItem::AVAIL_AVAILABLE;
                $item->admin_note = $adj['admin_note'] ?: null;
                $item->save();

                $total += (float) $item->subtotal;
                $included++;
            } else {
                $item->availability_status = BookingItem::AVAIL_UNAVAILABLE;
                $item->admin_note = ($adj['admin_note'] ?? null) ?: 'Item excluded from booking during availability review.';
                $item->save();

                $excluded++;
            }
        }

        $discount = (float) $booking->discount_amount;
        $surcharge = (float) $booking->tax_amount;
        $net = max(0.00, $total - $discount + $surcharge);

        $booking->total_amount = round($total, 2);
        $booking->net_amount = round($net, 2);
        $booking->save();

        return [
            'total' => round($total, 2),
            'net' => round($net, 2),
            'included' => $included,
            'excluded' => $excluded,
        ];
    }

    /**
     * Recreate cart items from a booking (used for rebooking after expiry/rejection/cancellation).
     */
    public function recreateCartFromBooking(Booking $booking, Request $request): int
    {
        $user = $request->user();
        $sessionToken = null;

        if (!$user) {
            $sessionToken = $request->session()->get('cart_session_token')
                ?: ('guest_' . md5($request->ip() . ($request->header('User-Agent') ?? 'ua')));
        }

        $count = 0;
        foreach ($booking->items()->where('availability_status', '!=', BookingItem::AVAIL_UNAVAILABLE)->get() as $item) {
            CartItem::create([
                'user_id' => $user?->id,
                'session_token' => $sessionToken,
                'item_type' => $item->item_type,
                'item_id' => $item->item_id,
                'quantity' => $item->quantity,
                'check_in_date' => $item->check_in_date,
                'check_out_date' => $item->check_out_date,
                'selected_pax' => $item->selected_pax,
                'is_selected' => true,
                'notes' => null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Generate a unique booking code (e.g. ST-2026-89412).
     */
    public function generateBookingCode(): string
    {
        do {
            $code = 'ST-' . date('Y') . '-' . strtoupper(Str::random(5));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }
}
