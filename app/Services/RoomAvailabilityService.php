<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RoomType;
use Carbon\Carbon;

class RoomAvailabilityService
{
    /**
     * Check room availability for a date range, counting bookings in hold statuses.
     *
     * @param  int|string|null  $excludeBookingId  booking whose own items should be ignored
     */
    public function check(RoomType $room, Carbon $checkIn, Carbon $checkOut, int|string|null $excludeBookingId = null): array
    {
        $bookedCount = BookingItem::where('item_type', 'room')
            ->where('item_id', $room->id)
            ->when($excludeBookingId, fn ($q) => $q->where('booking_id', '!=', $excludeBookingId))
            ->whereHas('booking', function ($q) {
                $q->whereIn('status', Booking::HOLD_STATUSES);
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

        return [
            'booked_count' => $bookedCount,
            'total_rooms' => $totalRooms,
            'remaining' => $remaining,
            'available' => $remaining > 0,
        ];
    }
}
