<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;

class MyBookingsController extends Controller
{
    /**
     * List the authenticated user's bookings (journal archive).
     */
    public function index()
    {
        $bookings = Booking::with(['items', 'reviews'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('booking.index', compact('bookings'));
    }
}
