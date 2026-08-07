<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingExpiryService;
use Illuminate\Http\Request;

class BookingConfirmationController extends Controller
{
    protected BookingExpiryService $expiryService;

    public function __construct(BookingExpiryService $expiryService)
    {
        $this->expiryService = $expiryService;
    }

    /**
     * Display the booking status page (request received / awaiting payment / voucher).
     */
    public function show($bookingCode, Request $request)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed within the 48-hour window.');
        }

        return view('booking.show', compact('booking'));
    }

    /**
     * Resolve a booking by code, restricted to its owner or an administrator.
     * 404 is returned (not 403) so booking codes cannot be probed.
     */
    protected function loadOwnedBooking(string $bookingCode): Booking
    {
        $booking = Booking::with(['items', 'user', 'history', 'reviews'])
            ->where('booking_code', $bookingCode)
            ->first();

        $isOwner = auth()->check() && $booking && $booking->user_id === auth()->id();
        $isAdmin = auth('admin')->check();

        if (! $booking || (! $isOwner && ! $isAdmin)) {
            abort(404);
        }

        return $booking;
    }
}
