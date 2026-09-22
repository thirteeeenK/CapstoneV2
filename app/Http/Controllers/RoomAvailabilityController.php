<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoomAvailabilityController extends Controller
{
    protected RoomAvailabilityService $availabilityService;

    public function __construct(RoomAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Check real-time room availability for requested check-in and check-out dates.
     */
    public function check(Request $request, $roomId)
    {
        try {
            $validated = $request->validate([
                'check_in' => 'required|date|after:today',
                'check_out' => 'required|date|after:check_in',
            ]);

            $room = RoomType::with('hotel')->findOrFail($roomId);

            $checkIn = Carbon::parse($validated['check_in'])->startOfDay();
            $checkOut = Carbon::parse($validated['check_out'])->startOfDay();
            $nights = max(1, $checkIn->diffInDays($checkOut));

            $availability = $this->availabilityService->check($room, $checkIn, $checkOut);

            // Unit rate & subtotal calculation
            $unitRate = (float) ($room->base_price ?? $room->rate_per_night ?? 0);
            $subtotal = $unitRate * $nights;

            return response()->json([
                'success' => true,
                'room_id' => $room->id,
                'room_name' => $room->room_name,
                'hotel_name' => $room->hotel ? ($room->hotel->hotel_name ?? $room->hotel->name) : null,
                'total_rooms' => $availability['total_rooms'],
                'booked_count' => $availability['booked_count'],
                'remaining_rooms' => $availability['remaining'],
                'available' => $availability['available'],
                'check_in' => $checkIn->format('Y-m-d'),
                'check_out' => $checkOut->format('Y-m-d'),
                'nights' => $nights,
                'rate_per_night' => $unitRate,
                'base_occupancy' => $room->base_occupancy,
                'max_occupancy' => $room->max_occupancy,
                'extra_person_fee' => (float) ($room->extra_person_fee ?: 0),
                'formatted_rate_per_night' => '₱'.number_format($unitRate, 2),
                'subtotal' => $subtotal,
                'formatted_subtotal' => '₱'.number_format($subtotal, 2),
                'message' => $availability['available']
                    ? "Available! {$availability['remaining']} room".($availability['remaining'] > 1 ? 's' : '')." left for {$nights} night".($nights > 1 ? 's' : '').'.'
                    : 'Sold out for the selected dates. Please choose alternative dates.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
