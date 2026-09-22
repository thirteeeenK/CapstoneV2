<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\Package;
use App\Models\PassengerCategoryRule;
use App\Models\RoomType;
use App\Notifications\BookingNotification;
use App\Notifications\BookingRequestReceived;
use App\Services\BookingRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    protected BookingRequestService $bookingRequestService;

    public function __construct(BookingRequestService $bookingRequestService)
    {
        $this->bookingRequestService = $bookingRequestService;
    }

    /**
     * Get active cart query for user/session.
     */
    protected function getCartQuery(Request $request)
    {
        return $this->bookingRequestService->getCartQuery($request);
    }

    /**
     * Render the Checkout Page.
     */
    public function index(Request $request)
    {
        $cartItems = $this->getCartQuery($request)
            ->with([
                'itemable' => function ($morphTo) {
                    $morphTo->morphWith([
                        RoomType::class => ['hotel', 'hotel.destination'],
                        ActivityModel::class => ['destination'],
                        AddOnModel::class => ['destination'],
                        Package::class => ['destination', 'rooms'],
                    ]);
                },
            ])
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index');
        }

        // Past-date guard: block checkout if any *selected* room has expired check-in (< today)
        $expiredSelected = $cartItems->filter(fn ($item) => $item->isExpired());
        if ($expiredSelected->isNotEmpty()) {
            $names = $expiredSelected->map(fn ($i) => $i->item_title.' ('.$i->date_details.')')->join(', ');

            return redirect()->route('cart.index')
                ->with('error', 'Your Trip Basket contains stays with dates that have already passed. Please update the dates or remove the items before checkout.'.($names ? ' Expired: '.$names : ''));
        }

        // Auto-heal/sync existing cart items in DB if quantity or pax was out of sync from older session
        foreach ($cartItems as $cItem) {
            if (in_array($cItem->item_type, ['activity', 'addon'])) {
                $maxVal = max(1, (int) $cItem->selected_pax, (int) $cItem->quantity);
                if ($cItem->selected_pax !== $maxVal || $cItem->quantity !== $maxVal) {
                    $cItem->selected_pax = $maxVal;
                    $cItem->quantity = $maxVal;
                    $cItem->save();
                }
            } elseif ($cItem->item_type === 'package') {
                // min_pax only gates booking eligibility at checkout, never inflates pax
                $maxVal = max(1, (int) $cItem->selected_pax, (int) $cItem->quantity);
                if ($cItem->selected_pax !== $maxVal || $cItem->quantity !== $maxVal) {
                    $cItem->selected_pax = $maxVal;
                    $cItem->quantity = $maxVal;
                    $cItem->save();
                }
            }
        }

        // Rooms contribute their base price only — any extra-person charges are
        // computed from the passenger manifests at checkout (see Extra Guest Charge).
        // Other item types keep their cart subtotal (already pax-aware).
        $totalAmount = 0.00;
        foreach ($cartItems as $cItem) {
            if ($cItem->item_type === 'room' && $cItem->itemable) {
                $nights = ($cItem->check_in_date && $cItem->check_out_date)
                    ? max(1, (int) $cItem->check_in_date->diffInDays($cItem->check_out_date))
                    : 1;
                $totalAmount += (float) $cItem->itemable->base_price * max(1, (int) $cItem->quantity) * $nights;
            } else {
                $totalAmount += (float) $cItem->subtotal;
            }
        }
        $netAmount = $totalAmount;

        // Fetch dynamic active category rules from database
        $categoryRules = PassengerCategoryRule::where('is_active', true)->get();

        return view('checkout.index', compact('cartItems', 'totalAmount', 'netAmount', 'categoryRules'));
    }

    /**
     * Submit a booking request from the selected cart items.
     */
    public function process(Request $request)
    {
        $validated = $request->validate([
            'contact_name' => 'required|string|max:150',
            'contact_email' => 'required|email|max:150',
            'contact_phone' => 'required|string|regex:/^\d{11}$/|max:30',
            'special_requests' => 'nullable|string|max:1000',
            'guest_manifest' => 'nullable|string',
        ], [
            'contact_phone.regex' => 'The mobile phone number must be exactly 11 digits.',
        ]);

        // Validate Package minimum pax
        $cartItems = $this->getCartQuery($request)->get();
        foreach ($cartItems as $cItem) {
            if ($cItem->item_type === 'package') {
                $minPax = (int) ($cItem->itemable->min_pax ?? 2);
                $pax = max((int) $cItem->selected_pax, (int) $cItem->quantity);
                if ($pax < $minPax) {
                    return response()->json([
                        'success' => false,
                        'message' => "Packages require a minimum of {$minPax} passengers.",
                    ], 422);
                }
            }
        }

        // Past-date guard for selected items (deselect to bypass)
        $expiredProcess = $cartItems->filter(fn ($item) => $item->isExpired());
        if ($expiredProcess->isNotEmpty()) {
            $names = $expiredProcess->map(fn ($i) => $i->item_title.' ('.($i->date_details ?: $i->check_in_date?->format('Y-m-d')).')')->join(', ');

            return response()->json([
                'success' => false,
                'message' => 'Your Trip Basket contains stays with dates that have already passed. Please update the dates or remove the items before checkout.'.($names ? ' Expired: '.$names : '').' You may also deselect the expired items to proceed with the remaining valid items.',
            ], 422);
        }

        try {
            $booking = $this->bookingRequestService->buildFromCart($request, $validated);

            BookingNotification::send(
                $booking,
                new BookingRequestReceived($booking)
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking request submitted! We will verify availability and email you once approved.',
                'redirect_url' => route('booking.show', $booking->booking_code),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit booking request: '.$e->getMessage(),
            ], 500);
        }
    }
}
