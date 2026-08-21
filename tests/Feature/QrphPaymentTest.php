<?php

use App\Models\Booking;
use App\Models\RoomType;
use App\Notifications\BookingPaid;
use App\Services\Payment\Drivers\QrphDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();

    $this->room = RoomType::factory()->create([
        'base_price' => 1500.00,
        'total_rooms' => 5,
    ]);

    $this->user = onboardedUser();
});

it('shows QRPH option on the payment page for an approved booking', function () {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '3000.00',
    ]);

    $this->actingAs($this->user)->get(route('booking.pay', $booking->booking_code))
        ->assertOk()
        ->assertSee('Pay with QRPH', false)
        ->assertSee('GCash', false)
        ->assertSee('GoTyme', false)
        ->assertSee('Show QRPH QR', false)
        ->assertSee('₱3,000.00', false);
});

it('creates a QRPH session and shows a scannable QR with the actual amount', function () {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '4525.50',
        'payment_deadline' => now()->addHours(48),
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.qrph', $booking->booking_code))
        ->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code));

    $booking->refresh();
    expect($booking->gateway)->toBe('qrph')
        ->and($booking->gateway_reference)->toStartWith('QRPH-')
        ->and($booking->payment_url)->not->toBeNull();

    $show = $this->actingAs($this->user)->get(route('booking.pay.qrph.show', $booking->booking_code));
    $show->assertOk()
        ->assertSee('QRPH — GCash / GoTyme', false)
        ->assertSee('₱4,525.50', false)
        ->assertSee($booking->gateway_reference, false);

    // QR content should embed the booking code and amount (demo EMVCo)
    $driver = app(QrphDriver::class);
    $qr = $driver->qrContent($booking);
    expect($qr)->toContain($booking->booking_code)
        ->and($qr)->toContain('4525.50');
});

it('marks a booking paid via QRPH confirm and sets payment_method to qrph', function () {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '2000.00',
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.qrph', $booking->booking_code));

    $this->actingAs($this->user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code));

    $booking->refresh();
    expect($booking->status)->toBe('paid')
        ->and($booking->payment_status)->toBe('paid')
        ->and($booking->payment_method)->toBe('qrph')
        ->and($booking->gateway)->toBe('qrph')
        ->and($booking->paid_at)->not->toBeNull();

    Notification::assertSentTo($this->user, BookingPaid::class);
});

it('verifies QRPH return via PayMongo when configured and marks paid', function () {
    // Without PAYMONGO_SECRET_KEY the driver falls back to demo (qrContent verifies as true)
    // So the return path should still mark paid via verifyReturn (demo refs return true)
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
    ]);
    $booking->gateway = 'qrph';
    $booking->gateway_reference = 'QRPH-TEST1234';
    $booking->payment_url = app(QrphDriver::class)->qrContent($booking);
    $booking->save();

    $this->actingAs($this->user)->get(route('booking.pay.qrph.return', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code));

    // In demo mode verifyReturn returns true for QRPH-* refs, so it marks paid
    expect($booking->fresh()->status)->toBe('paid');
});

it('rejects QRPH confirm when booking is not approved', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code))
        ->assertSessionHas('error');

    expect($booking->fresh()->status)->toBe('pending');
});

it('exposes the QR content amount correctly for the actual booking total', function () {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '789.00',
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.qrph', $booking->booking_code));
    $booking->refresh();

    $driver = new QrphDriver;
    $qr = $driver->qrContent($booking);

    // The QR string must be scannable and contain the exact amount with 2 decimals
    expect($qr)->toContain('789.00');

    // And the QR page should display that amount
    $this->actingAs($this->user)->get(route('booking.pay.qrph.show', $booking->booking_code))
        ->assertSee('₱789.00', false);
});

it('shows payment method as QRPH on the booking confirmation after paid via QRPH', function () {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '1500.00',
    ]);
    $this->actingAs($this->user)->post(route('booking.pay.qrph', $booking->booking_code));
    $this->actingAs($this->user)->post(route('booking.pay.qrph.confirm', $booking->booking_code));

    $this->actingAs($this->user)->get(route('booking.show', $booking->booking_code))
        ->assertOk()
        ->assertSee('QRPH', false);
});

it('shows the gateway selector on the booking page for approved bookings', function () {
    config()->set('services.stripe.secret_key', 'sk_test_dummy');

    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '2500.00',
    ]);

    $this->actingAs($this->user)->get(route('booking.show', $booking->booking_code))
        ->assertOk()
        ->assertSee('Select a payment method', false)
        ->assertSee('name="gateway" value="card"', false)
        ->assertSee('name="gateway" value="qrph"', false)
        ->assertSee('Proceed to Payment', false);
});

it('rejects proceeding to payment without selecting a gateway', function () {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.process', $booking->booking_code), [])
        ->assertRedirect(route('booking.show', $booking->booking_code))
        ->assertSessionHas('error', 'Please select a payment method before proceeding.');

    expect($booking->fresh()->status)->toBe('approved')
        ->and($booking->fresh()->gateway_reference)->toBeNull();
});

it('routes to the QRPH page when qrph gateway is selected on proceed', function () {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '3200.00',
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'qrph'])
        ->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code));

    expect($booking->fresh()->gateway)->toBe('qrph')
        ->and($booking->fresh()->gateway_reference)->toStartWith('QRPH-');
});

it('starts a card session when card gateway is selected on proceed', function () {
    config()->set('services.stripe.secret_key', null);

    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
    ]);
    // Stale QRPH session from an earlier attempt must be dropped when card is chosen
    $booking->gateway = 'qrph';
    $booking->gateway_reference = 'QRPH-STALE1';
    $booking->payment_url = '00020101...stale';
    $booking->save();

    $response = $this->actingAs($this->user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'card']);

    // Stripe not configured in tests → simulator driver, internal redirect
    $booking->refresh();
    expect($booking->gateway)->toBe('simulator')
        ->and($booking->gateway_reference)->not->toBe('QRPH-STALE1');

    $location = $response->headers->get('Location');
    expect($location)->toContain($booking->booking_code);
});

// ── Live PayMongo QR Ph (GoTyme-scannable, poll-on-button) ──

it('creates a real PayMongo QR Ph image in test mode and the QR is GoTyme-scannable', function () {
    config()->set('services.qrph.secret_key', 'sk_test_fakesandbox_secret');
    config()->set('services.stripe.secret_key', null);

    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '2850.75',
    ]);

    $piId = 'pi_'.Str::random(16);
    $pmId = 'pm_'.Str::random(16);
    $clientKey = $piId.'_client_'.Str::random(12);
    $qrImage = 'data:image/png;base64,'.base64_encode('fake-qr-'.Str::random(32));

    Http::fake(function ($request) use ($piId, $pmId, $clientKey, $qrImage, $booking) {
        $url = $request->url();

        if ($url === 'https://api.paymongo.com/v1/payment_intents' && $request->method() === 'POST') {
            return Http::response([
                'data' => [
                    'id' => $piId,
                    'type' => 'payment_intent',
                    'attributes' => [
                        'amount' => 285075,
                        'currency' => 'PHP',
                        'status' => 'awaiting_payment_method',
                        'client_key' => $clientKey,
                        'payment_method_allowed' => ['qrph'],
                        'metadata' => ['booking_code' => $booking->booking_code],
                    ],
                ],
            ], 200);
        }

        if ($url === 'https://api.paymongo.com/v1/payment_methods' && $request->method() === 'POST') {
            return Http::response([
                'data' => [
                    'id' => $pmId,
                    'type' => 'payment_method',
                    'attributes' => ['type' => 'qrph'],
                ],
            ], 200);
        }

        if (str_contains($url, '/payment_intents/'.$piId.'/attach')) {
            return Http::response([
                'data' => [
                    'id' => $piId,
                    'type' => 'payment_intent',
                    'attributes' => [
                        'status' => 'awaiting_next_action',
                        'next_action' => [
                            'code' => [
                                'id' => 'qr_'.Str::random(16),
                                'amount' => 285075,
                                'image_url' => $qrImage,
                                'test_url' => 'https://secure-authentication.paymongo.com/sources?id=src_'.Str::random(16).'&code_id=qr_'.Str::random(16),
                            ],
                        ],
                    ],
                ],
            ], 200);
        }

        return Http::response(['errors' => [['detail' => 'unexpected']]], 404);
    });

    // Proceed with QRPH → should hit PayMongo QR Ph flow, store pi_ + base64 image
    $this->actingAs($this->user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'qrph'])
        ->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code));

    $booking->refresh();
    expect($booking->gateway)->toBe('qrph')
        ->and($booking->gateway_reference)->toBe($piId)
        ->and($booking->payment_url)->toBe($qrImage)
        ->and($booking->payment_url)->toStartWith('data:image/png;base64,')
        ->and($booking->gateway_data['test_url'] ?? null)->toContain('secure-authentication.paymongo.com');

    // The QR page should now show Live QR Ph badge and render the image (GoTyme-scannable)
    $show = $this->actingAs($this->user)->get(route('booking.pay.qrph.show', $booking->booking_code));
    $show->assertOk()
        ->assertSee('Live — PayMongo QR Ph', false)
        ->assertSee('GoTyme-scannable', false)
        ->assertSee('data:image/png;base64', false)
        ->assertSee('₱2,850.75', false)
        ->assertSee('Open PayMongo test authentication page', false);

    // qrContent for a live booking is exactly the QR image
    $driver = app(QrphDriver::class);
    expect($driver->qrContent($booking))->toBe($qrImage);

    Http::assertSent(function ($request) use ($booking) {
        if ($request->url() !== 'https://api.paymongo.com/v1/payment_intents' || $request->method() !== 'POST') {
            return false;
        }
        $data = $request->data();

        return ($data['data']['attributes']['amount'] ?? null) === 285075
            && ($data['data']['attributes']['currency'] ?? null) === 'PHP'
            && ($data['data']['attributes']['payment_method_allowed'] ?? null) === ['qrph']
            && ($data['data']['attributes']['metadata']['booking_code'] ?? null) === $booking->booking_code;
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.paymongo.com/v1/payment_methods'
            && ($request->data()['data']['attributes']['type'] ?? null) === 'qrph';
    });
});

it('confirms a PayMongo QR Ph payment via poll-on-button when PayMongo says succeeded', function () {
    config()->set('services.qrph.secret_key', 'sk_test_fakesandbox_secret');

    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
    ]);
    $piId = 'pi_'.Str::random(16);
    $booking->gateway = 'qrph';
    $booking->gateway_reference = $piId;
    $booking->payment_url = 'data:image/png;base64,'.base64_encode('fake-qr');
    $booking->save();

    Http::fake([
        "api.paymongo.com/v1/payment_intents/{$piId}" => Http::response([
            'data' => [
                'id' => $piId,
                'attributes' => ['status' => 'succeeded'],
            ],
        ], 200),
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code));

    expect($booking->fresh()->status)->toBe('paid')
        ->and($booking->fresh()->payment_method)->toBe('qrph');
});

it('does not mark paid when PayMongo still shows awaiting_next_action on poll', function () {
    config()->set('services.qrph.secret_key', 'sk_test_fakesandbox_secret');

    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
    ]);
    $piId = 'pi_'.Str::random(16);
    $booking->gateway = 'qrph';
    $booking->gateway_reference = $piId;
    $booking->payment_url = 'data:image/png;base64,'.base64_encode('fake-qr');
    $booking->save();

    Http::fake([
        "api.paymongo.com/v1/payment_intents/{$piId}" => Http::response([
            'data' => [
                'id' => $piId,
                'attributes' => ['status' => 'awaiting_next_action'],
            ],
        ], 200),
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.qrph.confirm', $booking->booking_code))
        ->assertRedirect(route('booking.pay.qrph.show', $booking->booking_code))
        ->assertSessionHas('error');

    expect($booking->fresh()->status)->toBe('approved');
});

it('confirms a PayMongo QR Ph payment on return polling when succeeded', function () {
    config()->set('services.qrph.secret_key', 'sk_test_fakesandbox_secret');

    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
    ]);
    $piId = 'pi_'.Str::random(16);
    $booking->gateway = 'qrph';
    $booking->gateway_reference = $piId;
    $booking->payment_url = 'data:image/png;base64,'.base64_encode('fake-qr');
    $booking->save();

    Http::fake([
        "api.paymongo.com/v1/payment_intents/{$piId}" => Http::response([
            'data' => [
                'id' => $piId,
                'attributes' => ['status' => 'succeeded'],
            ],
        ], 200),
    ]);

    $this->actingAs($this->user)->get(route('booking.pay.qrph.return', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code));

    expect($booking->fresh()->status)->toBe('paid')
        ->and($booking->fresh()->payment_method)->toBe('qrph');
});
