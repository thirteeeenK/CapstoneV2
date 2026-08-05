<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\PassengerCategoryRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Get active cart query for user/session.
     */
    protected function getCartQuery(Request $request)
    {
        if (auth()->check()) {
            return CartItem::where('user_id', auth()->id())->where('is_selected', true);
        }

        $sessionToken = $request->hasSession() ? $request->session()->get('cart_session_token') : null;
        if (!$sessionToken) {
            $sessionToken = 'guest_' . md5($request->ip() . ($request->header('User-Agent') ?? 'ua'));
        }

        return CartItem::where('session_token', $sessionToken)->where('is_selected', true);
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
                        \App\Models\RoomType::class => ['hotel', 'hotel.destination'],
                        \App\Models\ActivityModel::class => ['destination'],
                        \App\Models\AddOnModel::class => ['destination'],
                        \App\Models\Package::class => ['destination'],
                    ]);
                }
            ])
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your Trip Basket is empty. Please add items before checking out.');
        }

        $totalAmount = $cartItems->sum('subtotal');
        $netAmount = $totalAmount;

        // Fetch dynamic active category rules from database
        $categoryRules = PassengerCategoryRule::where('is_active', true)->get();

        return view('checkout.index', compact('cartItems', 'totalAmount', 'netAmount', 'categoryRules'));
    }

    /**
     * Process & Finalize the Booking Checkout Order.
     */
    public function process(Request $request)
    {
        $validated = $request->validate([
            'contact_name' => 'required|string|max:150',
            'contact_email' => 'required|email|max:150',
            'contact_phone' => 'required|string|max:30',
            'special_requests' => 'nullable|string|max:1000',
            'payment_method' => 'required|string|in:GCash,Maya,Credit Card,Pay at Hotel',
            'guest_manifest' => 'nullable|string',
        ]);

        $cartItems = $this->getCartQuery($request)
            ->with(['itemable'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your Trip Basket is empty. Cannot process booking.',
            ], 422);
        }

        // Fetch active passenger category rules from database
        $rulesMap = PassengerCategoryRule::getActiveRulesMap();

        // Decode guest manifest JSON array & calculate pricing adjustments from database rules
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
                                $discountAmount += (float)$rule->amount;
                            } elseif ($rule->adjustment_type === 'surcharge') {
                                $surchargeAmount += (float)$rule->amount;
                            }
                        }
                    }
                }
            }
        }

        // Generate Unique Booking Code (e.g. ST-2026-89412)
        $bookingCode = 'ST-' . date('Y') . '-' . strtoupper(Str::random(5));

        $manifestCount = count($guestManifest);
        $totalAmount = 0.00;
        $calculatedItemSubtotals = [];

        foreach ($cartItems as $item) {
            $itemSubtotal = $item->subtotal;
            $effectivePax = max(1, (int)$item->selected_pax);

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

        DB::beginTransaction();
        try {
            // 1. Create Booking Record
            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'user_id' => auth()->id(),
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $surchargeAmount, // Using tax_amount column to store foreign surcharges
                'net_amount' => $netAmount,
                'payment_status' => $validated['payment_method'] === 'Pay at Hotel' ? 'unpaid' : 'paid',
                'payment_method' => $validated['payment_method'],
                'payment_reference' => 'REF-' . strtoupper(Str::random(8)),
                'contact_name' => $validated['contact_name'],
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
                'special_requests' => $validated['special_requests'] ?? null,
                'guest_manifest' => $guestManifest,
            ]);

            // 2. Create Booking Item Snapshot Records
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

            // 3. Clear Processed Items from Cart
            $cartItemIds = $cartItems->pluck('id')->toArray();
            CartItem::whereIn('id', $cartItemIds)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking order created successfully!',
                'redirect_url' => route('booking.success', $booking->booking_code),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize booking: ' . $e->getMessage(),
            ], 500);
        }
    }
}
