<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class BookingConfirmationController extends Controller
{
    /**
     * Display the official Booking Confirmation Voucher & Receipt.
     */
    public function show($bookingCode)
    {
        $booking = Booking::with('items')
            ->where('booking_code', $bookingCode)
            ->firstOrFail();

        return view('booking.show', compact('booking'));
    }
}
