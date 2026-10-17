<?php

use App\Models\Booking;
use App\Models\PaymentWebhookEvent;
use App\Models\QrphPaymentAttempt;
use App\Notifications\BookingPaid;
use App\Services\Payment\QrphPaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('services.payment.provider', 'auto');
    config()->set('services.qrph.mode', 'test');
    config()->set('services.qrph.secret_key', 'sk_test_fixture');
    config()->set('services.qrph.webhook_secret', 'fixture_webhook_secret');
});

it('settles nested signed PayMongo events exactly once across duplicate deliveries and event ids', function () {
    Notification::fake();
    $attempt = QrphPaymentAttempt::factory()->create();
    foreach (['evt_one', 'evt_one', 'evt_two'] as $id) {
        [$body, $headers] = paymongoWebhookFixture($attempt, 'payment.paid', $id);
        $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    }
    expect($attempt->booking->fresh())->payment_status->toBe('paid')->payment_reference->toBe('pay_fixture'.$attempt->id);
    expect(PaymentWebhookEvent::where('gateway', 'qrph')->count())->toBe(2);
    expect($attempt->booking->history()->where('to_status', 'paid')->count())->toBe(1);
    Notification::assertSentToTimes($attempt->booking->user, BookingPaid::class, 1);
    Http::assertNothingSent();
});

it('rejects invalid, outdated, wrong-mode and legacy webhook signatures', function (string $kind) {
    $this->freezeTime();
    $attempt = QrphPaymentAttempt::factory()->create();
    [$body, $headers] = paymongoWebhookFixture($attempt);
    if ($kind === 'tampered') {
        $body = str_replace('"paid"', '"failed"', $body);
    } elseif ($kind === 'missing') {
        unset($headers['HTTP_PAYMONGO_SIGNATURE']);
    } elseif ($kind === 'old') {
        $this->travel(6)->minutes();
    } elseif ($kind === 'wrong-mode') {
        $headers['HTTP_PAYMONGO_SIGNATURE'] = str_replace(',te=', ',li=', $headers['HTTP_PAYMONGO_SIGNATURE']);
    } elseif ($kind === 'legacy') {
        $headers['HTTP_PAYMONGO_SIGNATURE'] = str_replace(',te=', ',v1=', $headers['HTTP_PAYMONGO_SIGNATURE']);
    }
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertBadRequest();
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
    expect(PaymentWebhookEvent::count())->toBe(0);
})->with(['tampered', 'missing', 'old', 'wrong-mode', 'legacy']);

it('accepts li signatures only for live events without calling a live API', function () {
    productionPaymentContext();
    Notification::fake();
    config()->set('services.paymongo.secret_key', 'sk_live_fixture_not_a_real_key');
    config()->set('services.paymongo.webhook_secret', 'fixture_live_webhook_secret');
    $attempt = QrphPaymentAttempt::factory()->create(['livemode' => true]);
    [$body, $headers] = paymongoWebhookFixture($attempt);
    $this->call('POST', '/webhook/payment/paymongo', [], [], [], $headers, $body)->assertOk();
    expect($attempt->booking->fresh()->payment_status)->toBe('paid');
    Http::assertNothingSent();
});

it('refuses server responses that mismatch the booked amount, identity, currency, mode or payment source', function (string $field, mixed $value) {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    $intent = paymongoIntentFixture($attempt, 'succeeded');
    data_set($intent, $field, $value);
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response($intent)]);
    $this->actingAs($user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))->assertSessionHas('error');
    expect($booking->fresh()->payment_status)->toBe('unpaid');
    expect($attempt->fresh()->payment_id)->toBeNull();
})->with([
    ['data.id', 'pi_wrong'],
    ['data.attributes.amount', 1],
    ['data.attributes.currency', 'USD'],
    ['data.attributes.livemode', true],
    ['data.attributes.metadata.booking_code', 'ST-OTHER'],
    ['data.attributes.metadata.attempt_id', 'different-attempt'],
    ['data.attributes.payments.0.attributes.amount', 1],
    ['data.attributes.payments.0.attributes.currency', 'USD'],
    ['data.attributes.payments.0.attributes.payment_intent_id', 'pi_other'],
    ['data.attributes.payments.0.attributes.source.type', 'gcash'],
    ['data.attributes.payments.0.attributes.livemode', true],
    ['data.attributes.payments', []],
]);

it('rolls back the event receipt when a verified payment does not match the amount', function () {
    $attempt = QrphPaymentAttempt::factory()->create();
    [$body, $headers] = paymongoWebhookFixture($attempt);
    $data = json_decode($body, true);
    $data['data']['attributes']['data']['attributes']['amount'] = 1;
    $body = json_encode($data, JSON_THROW_ON_ERROR);
    $headers['HTTP_PAYMONGO_SIGNATURE'] = 't='.now()->timestamp.',te='.hash_hmac('sha256', now()->timestamp.'.'.$body, config('services.qrph.webhook_secret'));
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertStatus(503);
    expect(PaymentWebhookEvent::count())->toBe(0);
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
});

it('preserves retryability if the database fails during webhook processing', function () {
    Notification::fake();
    $attempt = QrphPaymentAttempt::factory()->create();
    [$body, $headers] = paymongoWebhookFixture($attempt);
    DB::statement("CREATE FUNCTION fail_qrph_receipt() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''receipt unavailable''; END'");
    DB::statement('CREATE TRIGGER fail_qrph_receipt BEFORE INSERT ON payment_webhook_events FOR EACH ROW EXECUTE FUNCTION fail_qrph_receipt()');
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertStatus(500);
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
    expect($attempt->fresh()->payment_id)->toBeNull();
    expect(PaymentWebhookEvent::count())->toBe(0);
    DB::statement('DROP TRIGGER fail_qrph_receipt ON payment_webhook_events');
    DB::statement('DROP FUNCTION fail_qrph_receipt()');
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($attempt->booking->fresh()->payment_status)->toBe('paid');
});

it('records failure or expiry without settling a booking or regressing a paid attempt', function (string $event, string $status) {
    Notification::fake();
    $attempt = QrphPaymentAttempt::factory()->create();
    [$body, $headers] = paymongoWebhookFixture($attempt, $event);
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($attempt->fresh()->status)->toBe($status);
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
    [$body, $headers] = paymongoWebhookFixture($attempt, 'payment.paid', 'evt_paid');
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    [$body, $headers] = paymongoWebhookFixture($attempt, $event, 'evt_late_failure');
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($attempt->fresh()->status)->toBe('paid');
})->with([['payment.failed', 'failed'], ['qrph.expired', 'expired']]);

it('records late payments for review without reopening cancelled or expired bookings', function (string $status) {
    Notification::fake();
    $booking = Booking::factory()->create(['status' => $status]);
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    [$body, $headers] = paymongoWebhookFixture($attempt);
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($booking->fresh())->status->toBe($status)->payment_status->toBe('unpaid');
    expect($attempt->fresh())->status->toBe('review_required')->payment_id->not->toBeNull();
    Notification::assertNothingSent();
})->with(['cancelled', 'expired', 'cancellation_requested']);

it('holds a confirmed payment for review if the booking total changed or its deadline passed', function (string $condition) {
    $booking = Booking::factory()->approved()->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    $booking->update($condition === 'price' ? ['net_amount' => '6500.00'] : ['payment_deadline' => now()->subMinute()]);
    [$body, $headers] = paymongoWebhookFixture($attempt);
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($attempt->fresh()->status)->toBe('review_required');
    expect($booking->fresh()->payment_status)->toBe('unpaid');
})->with(['price', 'deadline']);

it('does not create any payment from a GET request or trust manual demo confirmations', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'qrph', 'gateway_reference' => 'QRPH-DEMO']);
    $this->actingAs($user)->get(route('booking.pay.qrph.show', $booking->booking_code))->assertRedirect(route('booking.pay', $booking->booking_code));
    $this->post(route('booking.pay.qrph.confirm', $booking->booking_code))->assertSessionHas('error');
    $this->post(route('booking.pay.simulator.confirm', $booking->booking_code))->assertNotFound();
    expect(QrphPaymentAttempt::count())->toBe(0);
    expect($booking->fresh()->payment_status)->toBe('unpaid');
    Http::assertNothingSent();
});

it('hides payment creation and confirmation from another customer', function (string $route) {
    $booking = Booking::factory()->approved()->create();
    $this->actingAs(onboardedUser())->post(route($route, $booking->booking_code))->assertNotFound();
    Http::assertNothingSent();
})->with(['booking.pay.qrph', 'booking.pay.qrph.confirm']);

it('fails closed for absent or wrong-environment credentials without creating a demo', function (string $key) {
    config()->set('services.qrph.secret_key', $key);
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertSessionHas('error');
    expect(QrphPaymentAttempt::count())->toBe(0);
    expect($booking->fresh()->gateway_reference)->toBeNull();
    Http::assertNothingSent();
})->with(['', 'sk_live_wrong_environment']);

it('retains an uncertain attempt and retries creation with the same idempotency key', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $keys = [];
    Http::fake(function ($request) use (&$keys) {
        if ($request->url() === 'https://api.paymongo.com/v1/payment_intents') {
            $keys[] = $request->header('Idempotency-Key')[0];
            if (count($keys) === 1) {
                return Http::response(['errors' => [['detail' => 'private provider internal detail']]], 500);
            }

            return Http::response(paymongoIntentFixture(QrphPaymentAttempt::firstOrFail(), 'awaiting_payment_method'));
        }
        if ($request->url() === 'https://api.paymongo.com/v1/payment_methods') {
            return Http::response(['data' => ['id' => 'pm_fixture', 'attributes' => ['type' => 'qrph']]]);
        }

        return Http::response(paymongoIntentFixture(QrphPaymentAttempt::firstOrFail()));
    });
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertSessionHas('error');
    expect(session('error'))->not->toContain('private provider');
    $this->post(route('booking.pay.qrph', $booking->booking_code))->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code));
    expect($keys)->toHaveCount(2);
    expect($keys[0])->toBe($keys[1]);
    expect(QrphPaymentAttempt::count())->toBe(1);
});

it('keeps payments unpaid during connection failures with a readable error', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::failedConnection()]);
    $this->actingAs($user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))->assertSessionHas('error');
    expect($booking->fresh()->payment_status)->toBe('unpaid');
    expect($attempt->fresh()->status)->toBe('pending');
});

it('reuses a pending QR without making another intent or payment method', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt))]);
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertRedirect();
    expect(QrphPaymentAttempt::count())->toBe(1);
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('does not start a second attempt while another request holds the booking lock', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $lock = Cache::lock('qrph-booking-'.$booking->id, 60);
    $lock->get();
    try {
        $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertSessionHas('error');
        expect(QrphPaymentAttempt::count())->toBe(0);
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});

it('reconciles an existing successful intent without creating or charging anything', function () {
    Notification::fake();
    $attempt = QrphPaymentAttempt::factory()->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, 'succeeded'))]);
    $this->artisan('payments:reconcile-qrph', ['--gateway' => 'qrph'])->assertSuccessful();
    expect($attempt->booking->fresh()->payment_status)->toBe('paid');
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
});

it('marks expired QR attempts expired without marking a pending payment successful', function () {
    $attempt = QrphPaymentAttempt::factory()->create(['expires_at' => now()->subMinute()]);
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt))]);
    app(QrphPaymentService::class)->check($attempt);
    expect($attempt->fresh()->status)->toBe('expired');
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
});

it('handles a cancelled provider intent without treating it as payment', function () {
    $attempt = QrphPaymentAttempt::factory()->create();
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, 'cancelled'))]);
    app(QrphPaymentService::class)->check($attempt);
    expect($attempt->fresh()->status)->toBe('cancelled');
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
});

it('renews an expired QR on the same intent with a new idempotent method generation', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'qrph']);
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create(['status' => 'expired', 'expires_at' => now()->subMinute()]);
    Http::fake(function ($request) use ($attempt) {
        if ($request->method() === 'GET') {
            return Http::response(paymongoIntentFixture($attempt, 'awaiting_payment_method'));
        }
        if ($request->url() === 'https://api.paymongo.com/v1/payment_methods') {
            return Http::response(['data' => ['id' => 'pm_renewed', 'attributes' => ['type' => 'qrph']]]);
        }
        if ($request->url() === 'https://api.paymongo.com/v1/payment_intents/'.$attempt->intent_id.'/attach') {
            return Http::response(paymongoIntentFixture($attempt));
        }
        throw new LogicException('A second intent must never be created for QR renewal.');
    });
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertRedirect();
    expect($attempt->fresh())->method_id->toBe('pm_renewed')->method_generation->toBe(2)->status->toBe('pending');
    expect(QrphPaymentAttempt::count())->toBe(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $attempt->uuid.'-method-2'));
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v1/payment_intents');
});

it('does not renew a locally expired QR until PayMongo confirms it is no longer actionable', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attempt = QrphPaymentAttempt::factory()->for($booking)->create(['expires_at' => now()->subMinute()]);
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt))]);
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertSessionHas('error');
    expect(QrphPaymentAttempt::count())->toBe(1);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('recovers an attachment timeout by retrieving the existing QR rather than creating a second payment', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create();
    $attached = false;
    $attachCalls = 0;
    Http::fake(function ($request) use (&$attached, &$attachCalls) {
        $attempt = QrphPaymentAttempt::firstOrFail();
        if (str_ends_with($request->url(), '/payment_methods')) {
            return Http::response(['data' => ['id' => 'pm_fixture', 'attributes' => ['type' => 'qrph']]]);
        }
        if (str_ends_with($request->url(), '/attach')) {
            $attached = true;
            $attachCalls++;
            throw new ConnectionException('fixture timeout');
        }

        return Http::response(paymongoIntentFixture($attempt, $attached ? 'awaiting_next_action' : 'awaiting_payment_method'));
    });
    $this->actingAs($user)->post(route('booking.pay.qrph', $booking->booking_code))->assertSessionHas('error');
    $this->post(route('booking.pay.qrph', $booking->booking_code))->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code));
    expect(QrphPaymentAttempt::count())->toBe(1);
    expect(QrphPaymentAttempt::firstOrFail()->status)->toBe('pending');
    expect($attachCalls)->toBe(1);
    Http::assertSentCount(3);
});

it('keeps an in-flight payment pending after the QR display expiry', function () {
    $attempt = QrphPaymentAttempt::factory()->create(['expires_at' => now()->subMinute()]);
    Http::fake(['api.paymongo.com/v1/payment_intents/'.$attempt->intent_id => Http::response(paymongoIntentFixture($attempt, 'processing'))]);
    app(QrphPaymentService::class)->check($attempt);
    expect($attempt->fresh()->status)->toBe('pending');
    expect($attempt->booking->fresh()->payment_status)->toBe('unpaid');
});

it('does not expose an actionable QR after the booking payment deadline', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['payment_deadline' => now()->subMinute()]);
    QrphPaymentAttempt::factory()->for($booking)->create();
    $this->actingAs($user)->get(route('booking.pay.qrph.show', $booking->booking_code))->assertRedirect(route('booking.show', $booking->booking_code));
    Http::assertNothingSent();
});

it('does not confirm a live PayMongo booking through a simulator reference on the generic return', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->for($user)->create(['gateway' => 'paymongo', 'gateway_reference' => 'SIM-OLD']);
    $this->actingAs($user)->get(route('booking.pay.return', $booking->booking_code))->assertSessionHas('error');
    expect($booking->fresh()->payment_status)->toBe('unpaid');
});
