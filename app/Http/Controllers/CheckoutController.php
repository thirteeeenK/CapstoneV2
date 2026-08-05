<?php

namespace App\Http\Controllers;

use App\Models\PassengerCategoryRule;
use App\Services\BookingRequestService;
use Illuminate\Http\Request;

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
     * Submit a booking request from the selected cart items.
     */
    public function process(Request $request)
    {
        $validated = $request->validate([
            'contact_name' => 'required|string|max:150',
            'contact_email' => 'required|email|max:150',
            'contact_phone' => 'required|string|max:30',
            'special_requests' => 'nullable|string|max:1000',
            'guest_manifest' => 'nullable|string',
        ]);

        try {
            $booking = $this->bookingRequestService->buildFromCart($request, $validated);

            \App\Notifications\BookingNotification::send(
                $booking,
                new \App\Notifications\BookingRequestReceived($booking)
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking request submitted! We will verify availability and email you once approved.',
                'redirect_url' => route('booking.show', $booking->booking_code),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit booking request: ' . $e->getMessage(),
            ], 500);
        }
    }
}
