<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingExpiryService;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Expire approved bookings whose 48-hour payment window has passed';

    public function handle(BookingExpiryService $expiryService): int
    {
        $expired = 0;

        Booking::where('status', Booking::STATUS_APPROVED)
            ->whereNotNull('payment_deadline')
            ->where('payment_deadline', '<', now())
            ->chunkById(100, function ($bookings) use ($expiryService, &$expired) {
                foreach ($bookings as $booking) {
                    if ($expiryService->expireIfDue($booking)) {
                        $expired++;
                    }
                }
            });

        $this->info("Expired {$expired} booking(s) with passed payment deadlines.");

        return self::SUCCESS;
    }
}
