<?php

use App\Models\Booking;
use App\Models\QrphPaymentAttempt;
use App\Notifications\BookingPaid;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('services.payment.provider', 'auto');
    config()->set('services.stripe.secret_key', 'sk_test_stripe_fixture');
    config()->set('services.paymongo.secret_key', 'sk_live_paymongo_fixture');
    config()->set('services.qrph.mode', 'test');
    config()->set('services.qrph.secret_key', 'sk_test_fixture');
    config()->set('services.qrph.webhook_secret', 'fixture_webhook_secret');
});

it('shows sandbox QR Ph alongside Stripe, manual GoTyme and live PayMongo', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['net_amount' => '3000.00']);
    $this->actingAs($user)->get(route('booking.pay', $booking->booking_code))
        ->assertOk()->assertSee('QR Ph Sandbox')->assertSee('₱3,000.00', false)
        ->assertSee('Pay with Card')->assertSee('Pay to GoTyme QR')->assertSee('PayMongo QR Ph — Sandbox');
});

it('creates a PayMongo session with the exact centavo amount and stores a pending QR', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['net_amount' => '4525.50']);
    fakePaymongoCreation();

    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))
        ->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code));

    $attempt = QrphPaymentAttempt::firstOrFail();
    expect($booking->fresh())->gateway->toBe('qrph')->gateway_reference->toBe('pi_fixture')->payment_status->toBe('unpaid');
    expect($attempt)->amount->toBe(452550)->status->toBe('pending')->livemode->toBeFalse();
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v1/payment_intents'
        && $request['data']['attributes']['amount'] === 452550
        && $request['data']['attributes']['payment_method_allowed'] === ['qrph']
        && $request->hasHeader('Idempotency-Key', $attempt->uuid.'-intent'));
    $this->actingAs($user)->get(route('booking.pay.qrph.show', $booking->booking_code))
        ->assertOk()->assertSee('₱4,525.50', false)->assertSee('data:image/png;base64', false)->assertSee('Test mode');
});

it('marks the correct booking paid after verifying a matching paid QR Ph payment', function () {
    Notification::fake();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, 'succeeded'))]);

    $this->actingAs($user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code));
    expect($booking->fresh())->status->toBe('paid')->payment_status->toBe('paid')
        ->payment_method->toBe('qrph')->payment_reference->toBe('pay_fixture'.$attempt->id);
    expect($attempt->fresh())->status->toBe('paid')->confirmed_at->not->toBeNull();
    Notification::assertSentToTimes($user, BookingPaid::class, 1);
});

it('does not confirm legacy demo references on a QR Ph return', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'qrph', 'gateway_reference' => 'QRPH-TEST1234']);
    $this->actingAs($user)->get(route('booking.pay.qrph.return', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code))->assertSessionHas('error');
    expect($booking->fresh()->payment_status)->toBe('unpaid');
    Http::assertNothingSent();
});

it('rejects QR Ph creation and confirmation for a booking that is not approved', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->for($user)->create();
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertSessionHas('error');
    $this->post(route('booking.pay.qrph.confirm', $booking->booking_code))->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe('pending');
    Http::assertNothingSent();
});

it('never treats source chargeable as a completed QR Ph payment', function () {
    $attempt = QrphPaymentAttempt::factory()->create();
    [$body, $headers] = paymongoWebhookFixture($attempt, 'source.chargeable');
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertBadRequest();
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
});

it('keeps fractional and small booking amounts exact without rounding to another charge', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['net_amount' => '789.01']);
    fakePaymongoCreation();
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertRedirect();
    expect(QrphPaymentAttempt::firstOrFail()->amount)->toBe(78901);
    $this->get(route('booking.pay.qrph.show', $booking->booking_code))->assertSee('₱789.01', false);
});

it('shows QRPH as the payment method on the verified booking confirmation', function () {
    Notification::fake();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, 'succeeded'))]);
    $this->actingAs($user)->post(route('booking.pay.qrph.confirm', $booking->booking_code));
    $this->get(route('booking.show', $booking->booking_code))->assertOk()->assertSee('QRPH');
});

it('keeps all four payment choices visible when Stripe and live PayMongo are configured', function () {
    config()->set('services.stripe.secret_key', 'sk_test_fixture');
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $this->actingAs($user)->get(route('booking.show', $booking->booking_code))->assertOk()
        ->assertSee('name="gateway" value="qrph"', false)
        ->assertSee('name="gateway" value="card"', false)->assertSee('name="gateway" value="gotyme"', false)
        ->assertSee('name="gateway" value="paymongo"', false);
});

it('rejects proceeding to payment without selecting a gateway', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), [])
        ->assertRedirect(route('booking.show', $booking->booking_code))
        ->assertSessionHas('error', 'Please select a payment method before proceeding.');
    expect($booking->fresh()->gateway_reference)->toBeNull();
});

it('routes a selected QR Ph gateway through the existing booking checkout', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    fakePaymongoCreation();
    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'qrph'])
        ->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code));
    expect($booking->fresh()->gateway_reference)->toBe('pi_fixture');
});

it('refuses another checkout without discarding an active live PayMongo reference', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'paymongo', 'gateway_reference' => 'pi_existing']);
    QrphPaymentAttempt::factory()->for($booking)->create(['livemode' => true, 'intent_id' => 'pi_existing']);
    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'card'])->assertSessionHas('error');
    expect($booking->fresh()->gateway_reference)->toBe('pi_existing');
    Http::assertNothingSent();
});

it('labels sandbox QR images accurately and presents only the safe test authentication link', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create(['test_url' => 'https://secure-authentication.paymongo.com/fixture']);
    $this->actingAs($user)->get(route('booking.pay.qrph.show', $booking->booking_code))
        ->assertOk()->assertSee('Test mode')->assertSee('Do not scan or pay this QR code')
        ->assertSee('Open PayMongo test authentication page')->assertDontSee('Live payment')
        ->assertDontSee('sk_test_fixture')->assertDontSee('fixture_client_key');
    Http::assertNothingSent();
});

it('makes repeated payment confirmation a no-op with one history entry and notification', function () {
    Notification::fake();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, 'succeeded'))]);
    $this->actingAs($user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))->assertRedirect();
    $this->post(route('booking.pay.qrph.confirm', $booking->booking_code))->assertRedirect();
    expect($booking->history()->where('to_status', 'paid')->count())->toBe(1);
    Notification::assertSentToTimes($user, BookingPaid::class, 1);
    Http::assertSentCount(1);
});

it('keeps an awaiting-next-action QR Ph payment unpaid', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt))]);
    $this->actingAs($user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))
        ->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code))->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe('approved');
    expect($attempt->fresh()->status)->toBe('pending');
});

it('verifies payment on return using the stored intent rather than browser query parameters', function () {
    Notification::fake();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, 'succeeded'))]);
    $this->actingAs($user)->get(route('booking.pay.qrph.return', $booking->booking_code).'?status=paid&payment_intent_id=pi_attacker')->assertRedirect();
    expect($booking->fresh()->status)->toBe('paid');
    Http::assertSentCount(1);
});
