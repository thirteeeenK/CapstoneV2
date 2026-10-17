<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPaymentRequest;
use App\Models\BookingPaymentTransaction;
use App\Models\QrphPaymentAttempt;
use App\Services\Payment\BookingPaymentAccountingService;
use App\Services\Payment\BookingPaymentPolicy;
use App\Services\Payment\GoTymePaymentService;
use App\Services\Payment\InstallmentPaymentService;
use App\Services\Payment\PaymentEnvironmentResolver;
use App\Services\Payment\PaymentGatewayException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 21:12:00', 'Asia/Manila'));
    Notification::fake();
    Http::preventStrayRequests();
    config([
        'system_settings.payments.installments_enabled' => true,
        'services.stripe.secret_key' => 'sk_live_must_not_be_used_locally',
        'services.stripe.test_secret_key' => 'sk_test_flexible',
        'services.stripe.test_webhook_secret' => 'flexible_stripe_signing',
        'services.qrph.secret_key' => 'sk_test_flexible',
        'services.qrph.webhook_secret' => 'flexible_test_signing',
        'services.paymongo.secret_key' => 'sk_live_must_not_be_used_locally',
        'services.paymongo.webhook_secret' => 'flexible_live_signing',
    ]);
});

function flexiblePaymentBooking(?string $roomDate = null, ?string $tripDate = null): Booking
{
    $booking = Booking::factory()->approved()->for(onboardedUser())->create([
        'net_amount' => '8500.00', 'trip_itineraries' => $tripDate ? [['start_date' => $tripDate, 'days' => []]] : null,
    ]);
    BookingItem::factory()->create(['booking_id' => $booking->id, 'item_type' => 'activity', 'item_title' => 'Flexible island activity',
        'check_in_date' => null, 'check_out_date' => null, 'availability_status' => 'available']);
    if ($roomDate) {
        BookingItem::factory()->create(['booking_id' => $booking->id, 'item_type' => 'room', 'item_title' => 'Fixed hotel stay',
            'check_in_date' => $roomDate, 'check_out_date' => Carbon::parse($roomDate)->addDays(2), 'availability_status' => 'available']);
    }
    $booking->forceFill(app(BookingPaymentPolicy::class)->approvalData($booking))->save();

    return $booking->fresh();
}

test('undated activity bookings offer fixed deposit and an approval relative balance without assigning activity dates', function () {
    $booking = flexiblePaymentBooking();
    expect($booking)->payment_environment->toBe('test')->deposit_required_cents->toBe(425000);
    expect($booking->payment_policy)->toMatchArray(['deposit_eligible' => true, 'balance_rule' => 'after_approval', 'anchor_source' => 'approval']);
    expect($booking->payment_deadline->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-18 21:12:00');
    expect($booking->balance_due_at->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-23 21:12:00');
    expect($booking->items->first()->check_in_date)->toBeNull();
    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))->assertOk()
        ->assertSee('Pay 50% to reserve')->assertSee('4,250.00')->assertSee('configured window after approval');
});

test('undated activities do not block deposits anchored to a fixed room or confirmed trip', function (?string $room, ?string $trip, string $source) {
    $booking = flexiblePaymentBooking($room, $trip);
    expect($booking->payment_policy)->toMatchArray(['deposit_eligible' => true, 'balance_rule' => 'before_travel', 'anchor_source' => $source]);
    expect($booking->balance_due_at->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-30 09:00:00');
})->with([
    ['2026-11-01', null, 'fixed_service'],
    [null, '2026-11-01', 'confirmed_trip'],
    ['2026-11-03', '2026-11-01', 'confirmed_trip'],
]);

test('optional activity dates do not become fixed payment commitments', function () {
    $booking = flexiblePaymentBooking();
    $booking->items->first()->update(['check_in_date' => '2026-10-17', 'item_snapshot' => ['fixed_date' => false]]);
    $terms = app(BookingPaymentPolicy::class)->approvalData($booking->fresh());
    expect($terms['payment_policy']['balance_rule'])->toBe('after_approval');
    expect($terms['payment_policy']['deposit_eligible'])->toBeTrue();
    expect($booking->fresh()->balance_due_at->equalTo($booking->balance_due_at))->toBeTrue();
});

test('near term room bookings explain their real cutoff without blaming flexible activities', function () {
    config(['system_settings.payments.balance_lead_days' => 7]);
    $booking = flexiblePaymentBooking('2026-10-23');
    expect($booking->payment_policy['deposit_eligible'])->toBeFalse();
    $reason = app(BookingPaymentPolicy::class)->depositUnavailableReason($booking);
    expect($reason)->toContain('Oct 16, 2026 9:00 AM')->not->toContain('no scheduled dates');
    expect($booking->items->where('item_type', 'activity')->first()->check_in_date)->toBeNull();
});

test('extending an initial deadline preserves the Philippine time entered by the admin', function () {
    config(['app.timezone' => 'UTC']);
    $booking = flexiblePaymentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');

    $this->actingAs(settingsAdmin(), 'admin')->post(route('admin.bookings.payment-deadline', $booking->id), [
        'deadline' => '2026-10-20T21:12',
        'reason' => 'Customer requested additional time for the deposit.',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($booking->fresh()->payment_deadline->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-20 21:12:00');
    expect($request->fresh()->due_at->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-20 21:12:00');
});

test('initial and balance deadlines must be strictly ordered even for an explicit override', function () {
    $booking = flexiblePaymentBooking();
    expect(fn () => app(BookingPaymentPolicy::class)->approvalData($booking, $booking->balance_due_at->toIso8601String()))->toThrow(ValidationException::class);
});

test('flexible balance window settings reject a window no longer than initial collection', function () {
    $this->actingAs(settingsAdmin(), 'admin')->patch('/admin/settings/payments', [
        'section' => 'payments', 'revision' => 0,
        'values' => ['installments_enabled' => true, 'initial_window_hours' => 48, 'undated_balance_days' => 2, 'balance_lead_days' => 7, 'service_cutoff_hours' => 24],
    ])->assertSessionHasErrors('values.undated_balance_days');
});

test('local mode is always test despite a forged live selection and live configuration', function () {
    $this->app->instance('env', 'local');
    config(['services.payment.mode' => 'live']);
    $booking = flexiblePaymentBooking();
    $terms = app(BookingPaymentPolicy::class)->approvalData($booking, null, 'live');
    expect($terms['payment_environment'])->toBe('test');
    foreach (app(InstallmentPaymentService::class)->gatewayOptions($booking) as $option) {
        expect($option['enabled'])->toBeTrue();
    }
});

test('local PayMongo and QR Ph requests use only sandbox keys and complete separate deposits and balances', function (string $gateway) {
    $booking = flexiblePaymentBooking();
    installmentHttpFake();
    $service = app(InstallmentPaymentService::class);
    foreach (['deposit', 'balance'] as $purpose) {
        $request = $service->prepare($booking->fresh(), $purpose, $gateway);
        $service->start($request);
        $attempt = QrphPaymentAttempt::latest('id')->firstOrFail();
        expect($attempt)->amount->toBe(425000)->livemode->toBeFalse();
        [$body, $headers] = confirmInstallmentQr($attempt, 'evt_flexible_'.$gateway.'_'.$purpose);
        $this->call('POST', '/webhook/payment/'.$gateway, [], [], [], $headers, $body)->assertOk();
    }
    expect($booking->fresh()->payment_status)->toBe('paid');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_flexible:')));
    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_live_must_not_be_used_locally:')));
    expect(app(BookingPaymentAccountingService::class)->collectedBetween(now()->startOfDay(), now()->endOfDay()))->toBe(0.0);
})->with(['paymongo', 'qrph']);

test('local Stripe uses its test key for each installment and validates provider mode', function () {
    $booking = flexiblePaymentBooking();
    $service = app(InstallmentPaymentService::class);
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_flexible', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_flexible'])]);
    $request = $service->prepare($booking, 'deposit', 'stripe');
    $service->start($request);
    Http::assertSent(fn ($http) => $http->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_flexible:')) && $http['line_items[0][price_data][unit_amount]'] === 425000);
    $event = ['reference' => 'cs_test_flexible', 'payment_id' => 'pi_flexible', 'amount' => 425000, 'currency' => 'PHP', 'livemode' => true, 'payment_request_uuid' => $request->uuid];
    expect(fn () => $service->stripeReceipt($request->fresh(), $event))->toThrow(PaymentGatewayException::class);
    $service->stripeReceipt($request->fresh(), array_replace($event, ['livemode' => false]));
    expect($booking->fresh()->deposit_secured_at)->not->toBeNull();
    $balance = $service->prepare($booking->fresh(), 'balance', 'stripe');
    expect($balance)->amount->toBe(425000)->livemode->toBeFalse();
});

test('local manual GoTyme hides real bank instructions and credits only simulated verified money', function () {
    config(['services.gotyme.account_number' => 'DO_NOT_EXPOSE_REAL_BANK', 'services.gotyme.qr_image' => 'real-bank-qr.jpg']);
    $booking = flexiblePaymentBooking();
    $service = app(InstallmentPaymentService::class);
    foreach (['deposit', 'balance'] as $purpose) {
        $request = $service->prepare($booking->fresh(), $purpose, 'gotyme');
        $service->start($request);
        $this->actingAs($booking->user)->get(route('booking.pay.gotyme.show', $booking->booking_code))->assertOk()
            ->assertSee('GoTyme test submission')->assertSee('do not send real money')->assertDontSee('DO_NOT_EXPOSE_REAL_BANK')->assertDontSee('real-bank-qr.jpg');
        $payment = app(GoTymePaymentService::class)->submit($booking->fresh(), 'TEST-'.$purpose, '4250.00');
        app(GoTymePaymentService::class)->verify($booking->fresh(), $payment->id, '4250.00', settingsAdmin());
    }
    expect($booking->fresh()->payment_status)->toBe('paid');
    expect(BookingPaymentTransaction::where('livemode', true)->count())->toBe(0);
    Http::assertNothingSent();
});

test('missing local sandbox keys never fall back to configured live keys', function () {
    config(['services.qrph.secret_key' => '', 'services.stripe.test_secret_key' => null]);
    $booking = flexiblePaymentBooking();
    foreach (['paymongo', 'qrph', 'stripe'] as $gateway) {
        expect(fn () => app(InstallmentPaymentService::class)->prepare($booking, 'deposit', $gateway))->toThrow(PaymentGatewayException::class);
    }
    expect(BookingPaymentRequest::count())->toBe(0);
    Http::assertNothingSent();
});

test('legacy local checkout also uses test credentials and stamps its payment mode', function () {
    $booking = Booking::factory()->approved()->for(onboardedUser())->create();
    installmentHttpFake();
    $this->actingAs($booking->user)->post(route('booking.pay.process', $booking->booking_code), ['gateway' => 'paymongo'])->assertRedirect();
    expect(QrphPaymentAttempt::firstOrFail()->livemode)->toBeFalse();
    expect($booking->fresh()->payment_environment)->toBe('test');
    Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_live_must_not_be_used_locally:')));
});

test('production approvals resolve live and sandbox methods cannot credit their reservations', function () {
    $this->app->instance('env', 'production');
    $booking = flexiblePaymentBooking(null, '2026-11-01');
    expect($booking->payment_environment)->toBe('live');
    expect(app(InstallmentPaymentService::class)->gatewayOptions($booking)['paymongo']['enabled'])->toBeTrue();
    expect(fn () => app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'qrph'))->toThrow(PaymentGatewayException::class);
});

test('local correction previews without writes and applies repeat safely without resetting deadlines', function () {
    $booking = flexiblePaymentBooking();
    $booking->update(['payment_environment' => 'live']);
    $deadline = $booking->payment_deadline->toIso8601String();
    $this->artisan('payments:correct-local', ['booking' => [$booking->id]])->assertSuccessful();
    expect($booking->fresh()->payment_environment)->toBe('live');
    $this->artisan('payments:correct-local', ['booking' => [$booking->id], '--apply' => true])->assertSuccessful();
    $this->artisan('payments:correct-local', ['booking' => [$booking->id], '--apply' => true])->assertSuccessful();
    expect($booking->fresh()->payment_environment)->toBe('test');
    expect($booking->fresh()->payment_deadline->toIso8601String())->toBe($deadline);
    expect($booking->history()->count())->toBe(1);
});

test('local corrections refuse payment history and cannot run in production', function () {
    $booking = flexiblePaymentBooking();
    app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');
    $this->artisan('payments:correct-local', ['booking' => [$booking->id], '--apply' => true])->assertFailed();
    $this->app->instance('env', 'production');
    $this->artisan('payments:correct-local', ['booking' => [$booking->id], '--apply' => true])->assertFailed();
    expect(BookingPaymentRequest::count())->toBe(1);
});

test('unknown deployment environments fail closed until an explicit payment mode is configured', function () {
    $this->app->instance('env', 'staging');
    config(['services.payment.mode' => null]);
    expect(fn () => app(PaymentEnvironmentResolver::class)->mode())->toThrow(PaymentGatewayException::class);
});
