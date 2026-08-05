<?php

namespace App\Http\Controllers;

use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoomAvailabilityController extends Controller
{
    /**
     * Check real-time room availability for requested check-in and check-out dates.
     */
    public function check(Request $request, $roomId)
    {
        try {
            $validated = $request->validate([
                'check_in' => 'required|date|after_or_equal:today',
                'check_out' => 'required|date|after:check_in',
            ]);

            $room = RoomType::with('hotel')->findOrFail($roomId);

            $checkIn = Carbon::parse($validated['check_in'])->startOfDay();
            $checkOut = Carbon::parse($validated['check_out'])->startOfDay();
            $nights = max(1, $checkIn->diffInDays($checkOut));

            // Count existing confirmed/pending bookings overlapping requested date range
            $bookedCount = BookingItem::where('item_type', 'room')
                ->where('item_id', $roomId)
                ->whereHas('booking', function ($q) {
                    $q->whereIn('status', ['confirmed', 'pending']);
                })
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->whereBetween('check_in_date', [$checkIn, $checkOut->copy()->subDay()])
                        ->orWhereBetween('check_out_date', [$checkIn->copy()->addDay(), $checkOut])
                        ->orWhere(function ($sub) use ($checkIn, $checkOut) {
                            $sub->where('check_in_date', '<=', $checkIn)
                                ->where('check_out_date', '>=', $checkOut);
                        });
                })
                ->sum('quantity');

            $totalRooms = max(1, (int) ($room->total_number_of_rooms ?: $room->total_rooms ?: 5));
            $remaining = max(0, $totalRooms - $bookedCount);
            $isAvailable = $remaining > 0;

            // Unit rate & subtotal calculation
            $unitRate = (float) ($room->base_price ?? $room->rate_per_night ?? 0);
            $subtotal = $unitRate * $nights;

            return response()->json([
                'success' => true,
                'room_id' => $room->id,
                'room_name' => $room->room_name,
                'hotel_name' => $room->hotel ? ($room->hotel->hotel_name ?? $room->hotel->name) : null,
                'total_rooms' => $totalRooms,
                'booked_count' => $bookedCount,
                'remaining_rooms' => $remaining,
                'available' => $isAvailable,
                'check_in' => $checkIn->format('Y-m-d'),
                'check_out' => $checkOut->format('Y-m-d'),
                'nights' => $nights,
                'rate_per_night' => $unitRate,
                'formatted_rate_per_night' => '₱' . number_format($unitRate, 2),
                'subtotal' => $subtotal,
                'formatted_subtotal' => '₱' . number_format($subtotal, 2),
                'message' => $isAvailable
                    ? "Available! {$remaining} room" . ($remaining > 1 ? 's' : '') . " left for {$nights} night" . ($nights > 1 ? 's' : '') . '.'
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
