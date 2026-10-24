<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:expire')->hourly();
Schedule::command('payments:reconcile-qrph')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile-qrph --gateway=qrph')->everyMinute()->withoutOverlapping();
Schedule::command('payments:process')->everyMinute()->withoutOverlapping();
Schedule::command('trips:process-journey')->hourly()->withoutOverlapping()->when(fn () => config('services.trip_journey.enabled'));
Schedule::command('accounts:delete-expired')->everyMinute()->withoutOverlapping();
Schedule::command('trips:prune-guest-plans')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('trips:prune-itineraries')->dailyAt('03:45')->withoutOverlapping();
Schedule::command('lucky:prune-offers')->dailyAt('04:00')->withoutOverlapping();
