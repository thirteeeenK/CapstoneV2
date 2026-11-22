<?php

use App\Mail\TripJourneyMail;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Notifications\BookingCancellationApproved;
use App\Notifications\BookingPaymentDue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Mail::fake();
    $this->booking = Booking::factory()->approved()->create(['payment_deadline' => now()->addDay()]);
});

it('sends a booking notification to the given address on the mail channel only', function () {
    $this->artisan('mail:force', ['type' => 'payment-due', 'booking_code' => $this->booking->booking_code, '--email' => 'demo@test.dev'])
        ->expectsOutputToContain('Subject: Payment deadline')->assertSuccessful();

    Notification::assertSentOnDemand(BookingPaymentDue::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $channels === ['mail']
        && $notifiable->routes['mail'] === 'demo@test.dev' && $notification->kind === 'initial_due');
});

it('sends the cancellation approved email', function () {
    $this->artisan('mail:force', ['type' => 'cancellation-approved', 'booking_code' => $this->booking->booking_code, '--email' => 'demo@test.dev'])->assertSuccessful();

    Notification::assertSentOnDemand(BookingCancellationApproved::class);
});

it('force-sends a pre-trip email without recording a journey message', function () {
    $paid = Booking::factory()->paid()->create();
    BookingItem::factory()->create(['booking_id' => $paid->id, 'check_in_date' => now()->addDays(20), 'check_out_date' => now()->addDays(22), 'availability_status' => 'available']);

    $this->artisan('mail:force', ['type' => 'pre-trip', 'booking_code' => $paid->booking_code, '--email' => 'demo@test.dev'])->assertSuccessful();

    Mail::assertSent(TripJourneyMail::class, fn (TripJourneyMail $mail) => $mail->hasTo('demo@test.dev') && str_contains($mail->payload['subject'], $paid->booking_code));
    expect(DB::table('trip_journey_messages')->count())->toBe(0);
});

it('refuses to send when the input or booking does not fit', function (array $arguments) {
    $this->artisan('mail:force', array_merge(['booking_code' => $this->booking->booking_code], $arguments))->assertFailed();

    Notification::assertNothingSent();
    Mail::assertNothingSent();
})->with([
    'missing email' => [['type' => 'payment-due']],
    'unknown type' => [['type' => 'nope', '--email' => 'demo@test.dev']],
    'balance due without a deposit' => [['type' => 'balance-due', '--email' => 'demo@test.dev']],
    'pre-trip without dated services' => [['type' => 'pre-trip', '--email' => 'demo@test.dev']],
]);

it('refuses on production without the explicit flag', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('mail:force', ['type' => 'payment-due', 'booking_code' => $this->booking->booking_code, '--email' => 'demo@test.dev'])->assertFailed();

    Notification::assertNothingSent();
});
