<?php

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentWebhookEvent;
use App\Models\QrphPaymentAttempt;
use App\Services\Payment\Drivers\PaymongoDriver;
use App\Services\Payment\Drivers\QrphDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('services.payment.provider', 'auto');
    config()->set('services.stripe.secret_key', 'sk_test_stripe_fixture');
    config()->set('services.qrph.secret_key', 'sk_test_sandbox_fixture');
    config()->set('services.qrph.webhook_secret', 'sandbox_signing_fixture');
    config()->set('services.paymongo.secret_key', 'sk_live_paymongo_fixture');
    config()->set('services.paymongo.webhook_secret', 'live_signing_fixture');
});

it('shows four independently selectable payment choices regardless of the default provider', function (string $default) {
    config()->set('services.payment.provider', $default);
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();

    $this->actingAs($user)->get(route('booking.show', $booking->booking_code))->assertOk()
        ->assertSee('name="gateway" value="card"', false)
        ->assertSee('name="gateway" value="qrph"', false)
        ->assertSee('name="gateway" value="gotyme"', false)
        ->assertSee('name="gateway" value="paymongo"', false)
        ->assertSee('QR Ph Sandbox')->assertSee('PayMongo QR Ph — Sandbox');
})->with(['auto', 'stripe', 'qrph', 'paymongo']);

it('creates the separate PayMongo gateway with the live key and no sandbox fallback', function () {
    productionPaymentContext();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['net_amount' => '3210.55']);
    fakePaymongoCreation();

    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'paymongo'])
        ->assertRedirect(route('booking.pay.paymongo.show', $booking->booking_code));

    $attempt = QrphPaymentAttempt::firstOrFail();
    expect($attempt)->livemode->toBeTrue()->amount->toBe(321055)->test_url->toBeNull();
    expect($booking->fresh())->gateway->toBe('paymongo')->payment_status->toBe('unpaid');
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v1/payment_intents'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_live_paymongo_fixture:'))
        && $request['data']['attributes']['payment_method_allowed'] === ['qrph']);

    $this->get(route('booking.pay.paymongo.show', $booking->booking_code))->assertOk()
        ->assertSee('Live payment')->assertSee('PayMongo QR Ph')->assertSee('Scan with a participating bank')
        ->assertDontSee('Sandbox payment')->assertDontSee('Open PayMongo test authentication page')
        ->assertDontSee('sk_live_paymongo_fixture')->assertDontSee('fixture_client_key');
});

it('keeps sandbox QR Ph on its test key while live PayMongo is configured', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    fakePaymongoCreation();
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertRedirect();
    expect(QrphPaymentAttempt::firstOrFail()->livemode)->toBeFalse();
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_sandbox_fixture:')));
    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_live_paymongo_fixture:')));
});

it('routes a selected card to Stripe even when PayMongo is the default provider', function () {
    config()->set('services.payment.provider', 'paymongo');
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_fixture', 'url' => 'https://checkout.stripe.com/c/pay/cs_fixture'])]);
    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'card'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_fixture');
    expect($booking->fresh()->gateway)->toBe('stripe');
    expect(QrphPaymentAttempt::count())->toBe(0);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_stripe_fixture:')));
});

it('preserves the Stripe return and verified webhook with the PayMongo default', function (string $confirmation) {
    Notification::fake();
    config()->set('services.payment.provider', 'paymongo');
    config()->set('services.stripe.webhook_secret', 'stripe_signing_fixture');
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'stripe', 'gateway_reference' => 'cs_fixture']);

    if ($confirmation === 'return') {
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_fixture' => Http::response(['payment_status' => 'paid', 'livemode' => false])]);
        $this->actingAs($user)->get(route('booking.pay.return', $booking->booking_code))->assertRedirect();
    } else {
        $body = json_encode(['id' => 'evt_stripe', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_fixture', 'payment_status' => 'paid', 'client_reference_id' => $booking->booking_code,
            'amount_total' => 600000, 'currency' => 'php', 'livemode' => false,
        ]]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'stripe_signing_fixture')];
        $this->call('POST', '/webhook/payment/stripe', [], [], [], $headers, $body)->assertOk();
    }
    expect($booking->fresh()->payment_status)->toBe('paid');
})->with(['return', 'webhook']);

it('preserves manual GoTyme creation and submission with live PayMongo available', function () {
    config()->set('services.payment.provider', 'paymongo');
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();

    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'gotyme'])
        ->assertRedirect(route('booking.pay.gotyme.show', $booking->booking_code));
    $this->post(route('booking.pay.gotyme.submit', $booking->booking_code), ['sender_ref' => 'GOTYME-TEST-ISOLATION', 'claimed_amount' => '6000.00'])
        ->assertRedirect(route('booking.pay.gotyme.show', $booking->booking_code));
    expect($booking->fresh())->gateway->toBe('gotyme')->payment_status->toBe('unpaid');
    expect(BookingPayment::where('booking_id', $booking->id)->firstOrFail()->status)->toBe('submitted');
    Http::assertNothingSent();
});

it('allows a sandbox session to be replaced by an explicitly selected card without deleting its history', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'qrph', 'gateway_reference' => 'pi_sandbox']);
    $sandbox = QrphPaymentAttempt::factory()->for($booking)->create(['intent_id' => 'pi_sandbox']);
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_new', 'url' => 'https://checkout.stripe.com/c/pay/cs_new'])]);
    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'card'])->assertRedirect();
    expect($booking->fresh())->gateway->toBe('stripe')->gateway_reference->toBe('cs_new');
    expect($sandbox->fresh()->intent_id)->toBe('pi_sandbox');
});

it('keeps sandbox and live QR attempts distinct for the same booking', function () {
    productionPaymentContext();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'qrph', 'gateway_reference' => 'pi_sandbox']);
    $sandbox = QrphPaymentAttempt::factory()->for($booking)->create(['intent_id' => 'pi_sandbox']);
    fakePaymongoCreation();

    $this->actingAs($user)->post(route('booking.pay.paymongo', $booking->booking_code))->assertRedirect();
    expect(QrphPaymentAttempt::where('booking_id', $booking->id)->count())->toBe(2);
    expect($sandbox->fresh()->livemode)->toBeFalse();
    expect($booking->fresh())->gateway->toBe('paymongo')->gateway_reference->toBe('pi_livefixture');

    $this->get(route('booking.pay.qrph.show', $booking->booking_code))->assertViewHas('attempt', fn ($attempt) => $attempt->id === $sandbox->id);
    $this->get(route('booking.pay.paymongo.show', $booking->booking_code))->assertViewHas('attempt', fn ($attempt) => $attempt->livemode);
});

it('does not let a sandbox payment settle a booking whose live payment is active', function () {
    Notification::fake();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'paymongo']);
    $sandbox = QrphPaymentAttempt::factory()->for($booking)->create();
    $live = QrphPaymentAttempt::factory()->for($booking)->create(['livemode' => true]);
    [$body, $headers] = paymongoWebhookFixture($sandbox);
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($booking->fresh()->payment_status)->toBe('unpaid');
    expect($sandbox->fresh()->status)->toBe('review_required');
    expect($live->fresh()->status)->toBe('pending');
});

it('rejects sandbox signatures on the live endpoint and live signatures on the sandbox endpoint', function (bool $live) {
    productionPaymentContext();
    $attempt = QrphPaymentAttempt::factory()->create(['livemode' => $live]);
    [$body, $headers] = paymongoWebhookFixture($attempt);
    $this->call('POST', '/webhook/payment/'.($live ? 'qrph' : 'paymongo'), [], [], [], $headers, $body)->assertBadRequest();
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
    expect(PaymentWebhookEvent::count())->toBe(0);
})->with([true, false]);

it('verifies and deduplicates live PayMongo settlement without using the sandbox configuration', function () {
    productionPaymentContext();
    Notification::fake();
    $booking = Booking::factory()->approved()->create(['gateway' => 'paymongo']);
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create(['livemode' => true]);
    [$body, $headers] = paymongoWebhookFixture($attempt);
    foreach ([1, 2] as $delivery) {
        $this->call('POST', '/webhook/payment/paymongo', [], [], [], $headers, $body)->assertOk();
    }
    expect($booking->fresh())->payment_status->toBe('paid')->payment_method->toBe('paymongo');
    expect(PaymentWebhookEvent::where('gateway', 'paymongo')->count())->toBe(1);
    expect(PaymentWebhookEvent::where('gateway', 'qrph')->count())->toBe(0);
    Http::assertNothingSent();
});

it('confirms a live payment using the live credential and preserves pending status', function (string $status, string $expected) {
    productionPaymentContext();
    Notification::fake();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'paymongo']);
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create(['livemode' => true]);
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, $status))]);

    $this->actingAs($user)->post(route('booking.pay.paymongo.confirm', $booking->booking_code))->assertRedirect();
    expect($booking->fresh()->payment_status)->toBe($expected);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_live_paymongo_fixture:')));
})->with([['succeeded', 'paid'], ['awaiting_next_action', 'unpaid']]);

it('fails closed if the new live gateway is configured with a test key', function () {
    productionPaymentContext();
    config()->set('services.paymongo.secret_key', 'sk_test_wrong_for_live');
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $this->actingAs($user)->post(route('booking.pay.paymongo', $booking->booking_code))->assertSessionHas('error');
    expect(QrphPaymentAttempt::count())->toBe(0);
    Http::assertNothingSent();
    expect((new PaymongoDriver)->configured())->toBeFalse();
    expect((new QrphDriver)->configured())->toBeTrue();
});

it('rejects a live key for the sandbox even if a global mode is changed', function () {
    productionPaymentContext();
    config()->set('services.qrph.mode', 'live');
    config()->set('services.qrph.secret_key', 'sk_live_wrong_for_sandbox');
    expect((new QrphDriver)->configured())->toBeFalse();
    expect((new PaymongoDriver)->livemode())->toBeTrue();
});

it('keeps manual transfers from overwriting an in-flight live PayMongo payment', function (string $route) {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'paymongo']);
    QrphPaymentAttempt::factory()->for($booking)->create(['livemode' => true]);

    $this->actingAs($user)->post(route($route, $booking->booking_code), ['sender_ref' => 'TEST-REFERENCE', 'claimed_amount' => '6000.00'])
        ->assertSessionHas('error');
    expect($booking->fresh()->gateway)->toBe('paymongo');
    expect(BookingPayment::count())->toBe(0);
})->with(['booking.pay.gotyme', 'booking.pay.gotyme.submit']);

it('reconciles live intents by default without contacting sandbox intents', function () {
    productionPaymentContext();
    Notification::fake();
    $live = QrphPaymentAttempt::factory()->create(['livemode' => true]);
    $sandbox = QrphPaymentAttempt::factory()->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$live->intent_id => Http::response(paymongoIntentFixture($live, 'succeeded'))]);
    $this->artisan('payments:reconcile-qrph')->assertSuccessful();
    expect($live->booking->fresh()->payment_status)->toBe('paid');
    expect($sandbox->fresh()->status)->toBe('pending');
    Http::assertSentCount(1);
});

it('reports missing live signing configuration without displaying any credential', function () {
    productionPaymentContext();
    config()->set('services.paymongo.webhook_secret', null);
    config()->set('services.paymongo.webhook_url', 'https://sunnytripsv2.up.railway.app/webhook/payment/paymongo');
    config()->set('cache.default', 'database');
    $this->artisan('payments:check-configuration')
        ->expectsOutput('MISSING: Matching webhook signing secret is configured')->assertFailed();
    Http::assertNothingSent();
});

it('does not silently simulate Stripe on a provider outage', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::failedConnection()]);
    $this->actingAs($user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'card'])->assertSessionHas('error');
    expect($booking->fresh()->gateway)->toBeNull();
});

it('hides live payment creation and confirmation from another customer', function (string $route) {
    $booking = Booking::factory()->approved()->create();
    $this->actingAs(onboardedUser())->post(route($route, $booking->booking_code))->assertNotFound();
    Http::assertNothingSent();
})->with(['booking.pay.paymongo', 'booking.pay.paymongo.confirm']);
