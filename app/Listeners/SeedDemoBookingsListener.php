<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class SeedDemoBookingsListener
{
    public function handle(Registered $event): void
    {
        if (! config('app.demo_mode')) {
            return;
        }

        try {
            app(\App\Services\DemoSeedBookingService::class)->seed($event->user);
        } catch (\Throwable $e) {
            Log::warning('Demo seed bookings skipped', ['user_id' => $event->user->getKey(), 'error' => $e->getMessage()]);
        }
    }
}
