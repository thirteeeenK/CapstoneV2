<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoSeedBookingService
{
    public function __construct(private BookingRequestService $codes) {}

    public function seed(User $user): void
    {
        if ($user->bookings()->exists()) {
            return;
        }

        $room = RoomType::where('is_shown', true)->orderBy('base_price')->first();

        if (! $room) {
            Log::warning('Demo seed bookings skipped: no visible room', ['user_id' => $user->getKey()]);

            return;
        }

        $checkIn = now()->addDays(60)->toDateString();
        $checkOut = now()->addDays(61)->toDateString();
        $nightly = (float) $room->calculateNightlyRate(2);
        $realTotal = round($nightly * 2 * 1, 2);

        DB::transaction(function () use ($user, $room, $checkIn, $checkOut, $realTotal) {
            $this->makeApproved($user, [
                'item_type' => 'room',
                'item_id' => $room->getKey(),
                'item_title' => $room->room_name,
                'hotel_name' => $room->hotel?->hotel_name,
                'unit_price' => $realTotal,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total' => $realTotal,
                'special' => 'Auto demo seed — pay via sandbox to test full flow.',
            ]);

            $this->makeCompleted($user, [
                'item_type' => 'room',
                'item_id' => $room->getKey(),
                'item_title' => 'TEST — Demo stay (already completed, safe to review)',
                'hotel_name' => $room->hotel?->hotel_name,
                'unit_price' => 1.00,
                // Past stay so the completed state reads realistically in the review picker.
                'check_in' => now()->subDays(10)->toDateString(),
                'check_out' => now()->subDays(9)->toDateString(),
                'total' => 1.00,
                'special' => 'Demo seed — already completed, safe to review.',
            ]);
        });
    }

    private function makeApproved(User $user, array $item): Booking
    {
        $booking = Booking::create([
            'booking_code' => $this->codes->generateBookingCode(),
            'user_id' => $user->getKey(),
            'status' => Booking::STATUS_PENDING,
            'payment_status' => Booking::PAYMENT_UNPAID,
            'total_amount' => $item['total'],
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $item['total'],
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'contact_phone' => $user->phone_number ?? '09000000000',
            'special_requests' => $item['special'],
            'booking_source' => 'demo_seed',
        ]);

        BookingItem::create([
            'booking_id' => $booking->getKey(),
            'item_type' => $item['item_type'],
            'item_id' => $item['item_id'],
            'item_title' => $item['item_title'],
            'hotel_name' => $item['hotel_name'],
            'unit_price' => $item['unit_price'],
            'quantity' => 1,
            'selected_pax' => 2,
            'check_in_date' => $item['check_in'],
            'check_out_date' => $item['check_out'],
            'nights' => 1,
            'subtotal' => $item['total'],
            'availability_status' => BookingItem::AVAIL_AVAILABLE,
            'item_snapshot' => ['demo_seed' => true, 'room_id' => $item['item_id']],
        ]);

        $booking->transitionTo(
            Booking::STATUS_APPROVED,
            [Booking::STATUS_PENDING],
            [
                'approved_at' => now(),
                'payment_deadline' => now()->addDays(30),
                'admin_notes' => 'Auto demo seed',
            ],
            'Auto-approved demo seed',
            'demo_seed'
        );

        return $booking->refresh();
    }

    private function makeCompleted(User $user, array $item): Booking
    {
        $booking = Booking::create([
            'booking_code' => $this->codes->generateBookingCode(),
            'user_id' => $user->getKey(),
            'status' => Booking::STATUS_PENDING,
            'payment_status' => Booking::PAYMENT_UNPAID,
            'total_amount' => $item['total'],
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $item['total'],
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'contact_phone' => $user->phone_number ?? '09000000000',
            'special_requests' => $item['special'],
            'booking_source' => 'demo_seed',
        ]);

        BookingItem::create([
            'booking_id' => $booking->getKey(),
            'item_type' => $item['item_type'],
            'item_id' => $item['item_id'],
            'item_title' => $item['item_title'],
            'hotel_name' => $item['hotel_name'],
            'unit_price' => $item['unit_price'],
            'quantity' => 1,
            'selected_pax' => 2,
            'check_in_date' => $item['check_in'],
            'check_out_date' => $item['check_out'],
            'nights' => 1,
            'subtotal' => $item['total'],
            'availability_status' => BookingItem::AVAIL_AVAILABLE,
            'item_snapshot' => ['demo_seed' => true, 'room_id' => $item['item_id']],
        ]);

        $approvedAt = now()->subDays(10);

        $booking->transitionTo(
            Booking::STATUS_APPROVED,
            [Booking::STATUS_PENDING],
            [
                'approved_at' => $approvedAt,
                'payment_deadline' => (clone $approvedAt)->addDays(30),
                'admin_notes' => 'Auto demo seed',
            ],
            'Auto-approved demo seed',
            'demo_seed'
        );

        $booking->transitionTo(
            Booking::STATUS_PAID,
            [Booking::STATUS_APPROVED],
            ['paid_at' => now()->subDays(9), 'payment_status' => Booking::PAYMENT_PAID],
            'Demo seed payment (sandbox simulation)',
            'demo_seed'
        );

        $booking->transitionTo(
            Booking::STATUS_COMPLETED,
            [Booking::STATUS_PAID],
            [],
            'Auto-completed demo seed — reviewable',
            'demo_seed'
        );

        return $booking->refresh();
    }
}
