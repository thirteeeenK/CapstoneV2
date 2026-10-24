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
                $q->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn);
            })
            ->sum('quantity');

        $totalRooms = $this->totalRooms($room);
        $remaining = max(0, $totalRooms - $bookedCount);

        return [
            'booked_count' => $bookedCount,
            'total_rooms' => $totalRooms,
            'remaining' => $remaining,
            'available' => $remaining > 0,
        ];
    }

    /**
     * The room type's stock, assuming 5 when a legacy record has none set.
     */
    public function totalRooms(RoomType $room): int
    {
        return max(1, (int) ($room->total_number_of_rooms ?: $room->total_rooms ?: 5));
    }
}
