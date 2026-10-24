<?php

use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPaymentRequest;
use App\Models\BookingPaymentTransaction;
use App\Models\QrphPaymentAttempt;
use App\Notifications\BookingPaymentDue;
use App\Notifications\BookingPaymentReceived;
use App\Services\BookingExpiryService;
use App\Services\Payment\BookingPaymentAccountingService;
use App\Services\Payment\BookingPaymentPolicy;
use App\Services\Payment\GoTymePaymentService;
use App\Services\Payment\InstallmentPaymentService;
use App\Services\Payment\PaymentGatewayException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Notification::fake();
    Http::preventStrayRequests();
    config([
        'system_settings.payments.installments_enabled' => true,
        'services.paymongo.secret_key' => 'sk_live_installment_fixture',
        'services.paymongo.webhook_secret' => 'live_installment_signing',
        'services.qrph.secret_key' => 'sk_test_installment_fixture',
        'services.qrph.webhook_secret' => 'test_installment_signing',
        'services.stripe.secret_key' => 'sk_test_stripe_fixture',
        'services.stripe.webhook_secret' => 'stripe_installment_signing',
    ]);
});

function installmentBooking(string $amount = '10000.00', int $arrivalDays = 30): Booking
{
    $booking = Booking::factory()->approved()->for(onboardedUser())->create(['net_amount' => $amount]);
    BookingItem::create([
        'booking_id' => $booking->id, 'item_type' => 'activity', 'item_id' => 999999,
        'item_title' => 'Reserved experience', 'unit_price' => $amount, 'quantity' => 1,
        'selected_pax' => 1, 'subtotal' => $amount, 'availability_status' => 'available',
        'item_snapshot' => ['fixed_date' => true],
        'check_in_date' => now('Asia/Manila')->addDays($arrivalDays)->toDateString(),
    ]);
    $booking->forceFill(app(BookingPaymentPolicy::class)->approvalData($booking))->save();

    return $booking->fresh();
}

function installmentHttpFake(): void
{
    Http::fake(function ($request) {
        $attempt = QrphPaymentAttempt::latest('id')->firstOrFail();
        if (str_ends_with($request->url(), '/payment_methods')) {
            return Http::response(['data' => ['id' => 'pm_installment'.$attempt->id, 'attributes' => ['type' => 'qrph']]]);
        }
        $data = paymongoIntentFixture($attempt, str_ends_with($request->url(), '/payment_intents') ? 'awaiting_payment_method' : 'awaiting_next_action');
        $data['data']['id'] = $attempt->intent_id ?: 'pi_installment'.$attempt->id;

        return Http::response($data);
    });
}

function confirmInstallmentQr(QrphPaymentAttempt $attempt, string $eventId): array
{
    [$body] = paymongoWebhookFixture($attempt, 'payment.paid', $eventId);
    $payload = json_decode($body, true);
    $payload['data']['attributes']['data']['id'] = 'pay_installment'.$attempt->id;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $t = now()->timestamp;
    $field = $attempt->livemode ? 'li' : 'te';
    $secret = config('services.'.($attempt->livemode ? 'paymongo' : 'qrph').'.webhook_secret');

    return [$body, ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't='.$t.','.$field.'='.hash_hmac('sha256', $t.'.'.$body, $secret)]];
}

test('a deposit and separate balance create two intents and credit the booking exactly once', function () {
    $booking = installmentBooking();
    installmentHttpFake();
    $this->actingAs($booking->user)->post(route('booking.installment.store', $booking->booking_code), ['purpose' => 'deposit', 'gateway' => 'paymongo'])->assertRedirect();
    $first = QrphPaymentAttempt::firstOrFail();
    expect($first->amount)->toBe(500000);
    [$body, $headers] = confirmInstallmentQr($first, 'evt_installment1');
    $this->call('POST', '/webhook/payment/paymongo', [], [], [], $headers, $body)->assertOk();
    $this->call('POST', '/webhook/payment/paymongo', [], [], [], $headers, $body)->assertOk();
    expect($booking->fresh())->status->toBe('approved')->payment_status->toBe('partial')->deposit_secured_at->not->toBeNull()->paid_at->toBeNull();
    $this->get(route('booking.show', $booking->booking_code))->assertOk()->assertSee('Reserved · Partially paid')->assertSee('5,000.00');
    $this->travel(3)->days();
    expect(app(BookingExpiryService::class)->expireIfDue($booking->fresh()))->toBeFalse();
    $this->post(route('booking.installment.store', $booking->booking_code), ['purpose' => 'balance', 'gateway' => 'paymongo'])->assertRedirect();
    $second = QrphPaymentAttempt::latest('id')->firstOrFail();
    expect($second->id)->not->toBe($first->id);
    expect($second->intent_id)->not->toBe($first->intent_id);
    [$body, $headers] = confirmInstallmentQr($second, 'evt_installment2');
    $this->call('POST', '/webhook/payment/paymongo', [], [], [], $headers, $body)->assertOk();
    expect($booking->fresh())->status->toBe('paid')->payment_status->toBe('paid')->paid_at->not->toBeNull();
    expect(BookingPaymentTransaction::count())->toBe(2);
    expect(app(BookingPaymentAccountingService::class)->summary($booking->fresh()))->toMatchArray(['verified_cents' => 1000000, 'remaining_cents' => 0]);
});

test('full payment charges the whole amount while a forged browser amount is ignored', function () {
    $booking = installmentBooking();
    installmentHttpFake();
    $this->actingAs($booking->user)->post(route('booking.installment.store', $booking->booking_code), ['purpose' => 'full', 'gateway' => 'paymongo', 'amount' => 1])->assertRedirect();
    expect(QrphPaymentAttempt::firstOrFail()->amount)->toBe(1000000);
});

test('policy handles odd centavos and near-term full payment eligibility', function () {
    expect(installmentBooking('1000.01')->deposit_required_cents)->toBe(50001);
    $near = installmentBooking('10000.00', 3);
    expect($near->payment_policy['deposit_eligible'])->toBeFalse();
    expect(fn () => app(InstallmentPaymentService::class)->prepare($near, 'deposit', 'paymongo'))->toThrow(PaymentGatewayException::class);
    expect(installmentBooking('1.99')->payment_policy['deposit_eligible'])->toBeFalse();
});

test('another tab cannot open a conflicting amount or payment method', function () {
    $booking = installmentBooking();
    $service = app(InstallmentPaymentService::class);
    $first = $service->prepare($booking, 'deposit', 'paymongo');
    expect($service->prepare($booking, 'deposit', 'paymongo')->id)->toBe($first->id);
    expect(fn () => $service->prepare($booking, 'full', 'stripe'))->toThrow(PaymentGatewayException::class);
    expect(BookingPaymentRequest::count())->toBe(1);
});

test('database uniqueness prevents a second unresolved collection', function () {
    $booking = installmentBooking();
    $first = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    expect(fn () => DB::transaction(fn () => BookingPaymentRequest::create(array_merge($first->getAttributes(), ['id' => null, 'uuid' => (string) Str::uuid()]))))->toThrow(QueryException::class);
});

test('sandbox receipts cannot secure a live reservation', function () {
    $this->app->instance('env', 'production');
    $booking = installmentBooking();
    expect(fn () => app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'qrph'))->toThrow(PaymentGatewayException::class);
    expect($booking->fresh()->deposit_secured_at)->toBeNull();
    expect(BookingPaymentTransaction::count())->toBe(0);
});

test('manual submissions count only after staff verify them and may be followed by another gateway', function () {
    $booking = installmentBooking();
    $collection = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');
    app(InstallmentPaymentService::class)->start($collection);
    $payment = app(GoTymePaymentService::class)->submit($booking, 'GT-DEPOSIT-1', '5000.00');
    expect(app(BookingPaymentAccountingService::class)->summary($booking)['verified_cents'])->toBe(0);
    expect(fn () => app(InstallmentPaymentService::class)->prepare($booking, 'full', 'paymongo'))->toThrow(PaymentGatewayException::class);
    app(GoTymePaymentService::class)->verify($booking, $payment->id, '5000.00', settingsAdmin());
    expect($booking->fresh()->deposit_secured_at)->not->toBeNull();
    $balance = app(InstallmentPaymentService::class)->prepare($booking->fresh(), 'balance', 'paymongo');
    expect($balance->amount)->toBe(500000);
});

test('a below-threshold verified transfer does not secure a reservation or get silently expired', function () {
    $booking = installmentBooking();
    $collection = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');
    app(InstallmentPaymentService::class)->start($collection);
    $payment = app(GoTymePaymentService::class)->submit($booking, 'GT-SHORT-1', '5000.00');
    app(GoTymePaymentService::class)->verify($booking, $payment->id, '1000.00', settingsAdmin());
    expect($booking->fresh())->deposit_secured_at->toBeNull()->payment_status->toBe('partial');
    $this->travel(3)->days();
    expect(app(BookingExpiryService::class)->expireIfDue($booking->fresh()))->toBeFalse();
    expect($booking->fresh()->balance_overdue_at)->not->toBeNull();
});

test('different webhook events for one payment cannot credit twice', function () {
    $booking = installmentBooking();
    installmentHttpFake();
    $collection = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    app(InstallmentPaymentService::class)->start($collection);
    $attempt = QrphPaymentAttempt::firstOrFail();
    foreach (['evt_receipt_a', 'evt_receipt_b'] as $id) {
        [$body,$headers] = confirmInstallmentQr($attempt, $id);
        $this->call('POST', '/webhook/payment/paymongo', [], [], [], $headers, $body)->assertOk();
    }
    expect(BookingPaymentTransaction::count())->toBe(1);
});

test('cancelled bookings retain incoming money for review without reopening inventory', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $booking->update(['status' => 'cancelled']);
    $receipt = app(BookingPaymentAccountingService::class)->receipt($request, 'pay_late', 500000);
    expect($receipt->allocated)->toBeFalse();
    expect($booking->fresh()->status)->toBe('cancelled');
    expect($request->fresh()->status)->toBe('review_required');
});

test('a receipt during a cancellation review is held for review and denial reopens payment', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $booking->update(['status' => 'cancellation_requested', 'cancellation_requested_from' => 'approved', 'cancellation_requested_at' => now(), 'cancellation_request_reason' => 'Our travel dates changed unexpectedly.']);

    $receipt = app(BookingPaymentAccountingService::class)->receipt($request, 'pay_during_review', 500000);
    expect($receipt->allocated)->toBeFalse()->and($request->fresh()->status)->toBe('review_required');

    $admin = AdminModel::create(['name' => 'Admin Review', 'email' => 'admin-review-installment@test.com', 'password' => 'password']);
    $this->actingAs($admin, 'admin')->post(route('admin.bookings.cancel-request.deny', $booking->id), ['admin_message' => 'Travel dates stay as booked; no change needed.']);

    expect($booking->fresh()->status)->toBe('approved');
    app(BookingPaymentPolicy::class)->assertCollectible($booking->fresh());
});

test('unpaid bookings without an open collection expire at their snapshotted deadline', function () {
    $booking = installmentBooking();
    $this->travel(3)->days();
    expect(app(BookingExpiryService::class)->expireIfDue($booking))->toBeTrue();
    expect($booking->fresh()->status)->toBe('expired');
});

test('a funded booking becomes overdue only at its balance deadline', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    app(BookingPaymentAccountingService::class)->receipt($request, 'pay_deposit', 500000);
    $this->travelTo($booking->fresh()->balance_due_at->copy()->addMinute());
    expect(app(BookingExpiryService::class)->expireIfDue($booking->fresh()))->toBeFalse();
    expect($booking->fresh())->status->toBe('approved')->balance_overdue_at->not->toBeNull();
    expect(fn () => app(InstallmentPaymentService::class)->prepare($booking->fresh(), 'balance', 'paymongo'))->toThrow(PaymentGatewayException::class);
});

test('legacy return and direct QR creation cannot mark a deposit booking paid', function () {
    $booking = installmentBooking();
    $this->actingAs($booking->user)->get(route('booking.pay.return', $booking->booking_code))->assertRedirect(route('booking.pay', $booking->booking_code));
    $this->post(route('booking.pay.paymongo', $booking->booking_code))->assertRedirect(route('booking.pay', $booking->booking_code));
    expect($booking->fresh()->payment_status)->toBe('unpaid');
    Http::assertNothingSent();
});

test('owners cannot pay or inspect another customers collection', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $this->actingAs(onboardedUser())->get(route('booking.installment.show', [$booking->booking_code, $request->uuid]))->assertNotFound();
    $this->post(route('booking.installment.store', $booking->booking_code), ['purpose' => 'deposit', 'gateway' => 'paymongo'])->assertNotFound();
});

test('stripe receipt verification uses the immutable installment rather than the booking total', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'stripe');
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_live_installment', 'url' => 'https://checkout.stripe.com/c/pay/cs_live_installment'])]);
    app(InstallmentPaymentService::class)->start($request);
    Http::assertSent(fn ($http) => $http['line_items[0][price_data][unit_amount]'] === 500000);
    $event = ['reference' => 'cs_live_installment', 'payment_id' => 'pi_stripe_installment', 'amount' => 500000, 'currency' => 'PHP', 'livemode' => false, 'payment_request_uuid' => $request->uuid];
    app(InstallmentPaymentService::class)->stripeReceipt($request->fresh(), $event);
    expect($booking->fresh()->payment_status)->toBe('partial');
    expect(fn () => app(InstallmentPaymentService::class)->stripeReceipt($request, array_replace($event, ['amount' => 1000000])))->toThrow(PaymentGatewayException::class);
});

test('verified external refunds retain the receipt and cannot exceed its original amount', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $receipt = app(BookingPaymentAccountingService::class)->receipt($request, 'pay_refundable', 500000);
    $admin = settingsAdmin();
    app(BookingPaymentAccountingService::class)->recordRefund($booking, $receipt, 100000, 'refund_1', $admin, 'Bank refund verified.');
    expect(app(BookingPaymentAccountingService::class)->summary($booking)['verified_cents'])->toBe(400000);
    expect(BookingPaymentTransaction::count())->toBe(2);
    expect(fn () => app(BookingPaymentAccountingService::class)->recordRefund($booking, $receipt, 500000, 'refund_2', $admin, 'Too much.'))->toThrow(PaymentGatewayException::class);
});

test('admin settings save validates durations and preserves previously approved deadlines', function () {
    $booking = installmentBooking();
    $original = $booking->payment_deadline->toIso8601String();
    $this->actingAs(settingsAdmin(), 'admin')->patch('/admin/settings/payments', [
        'section' => 'payments', 'revision' => 0,
        'values' => ['installments_enabled' => true, 'initial_window_hours' => 72, 'balance_lead_days' => 5, 'undated_balance_days' => 7, 'service_cutoff_hours' => 24],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($booking->fresh()->payment_deadline->toIso8601String())->toBe($original);
    expect(app(BookingPaymentPolicy::class)->approvalData($booking)['payment_deadline']->diffInHours(now(), true))->toBeGreaterThan(71);
    $this->patch('/admin/settings/payments', ['section' => 'payments', 'revision' => 1, 'values' => ['installments_enabled' => true, 'initial_window_hours' => 0, 'balance_lead_days' => -1, 'service_cutoff_hours' => 0]])->assertSessionHasErrors();
});

test('admin can extend a reservation deadline without changing its quote or receipts', function () {
    $booking = installmentBooking();
    $before = $booking->payment_deadline;
    $this->actingAs(settingsAdmin(), 'admin')->post(route('admin.bookings.payment-deadline', $booking->id), ['deadline' => $before->copy()->addHours(12)->toIso8601String(), 'reason' => 'Customer requested an extension.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($booking->fresh()->payment_deadline->gt($before))->toBeTrue();
    expect(BookingPaymentTransaction::count())->toBe(0);
});

test('durable receipt notifications are queued only once across processor retries', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    app(BookingPaymentAccountingService::class)->receipt($request, 'pay_notice', 500000);
    $this->artisan('payments:process')->assertSuccessful();
    $this->artisan('payments:process')->assertSuccessful();
    Notification::assertSentToTimes($booking->user, BookingPaymentReceived::class, 1);
});

test('a failed balance request does not undo the secured deposit', function () {
    $booking = installmentBooking();
    $service = app(InstallmentPaymentService::class);
    $deposit = $service->prepare($booking, 'deposit', 'paymongo');
    app(BookingPaymentAccountingService::class)->receipt($deposit, 'pay_secured', 500000);
    $balance = $service->prepare($booking->fresh(), 'balance', 'paymongo');
    $balance->update(['status' => 'failed']);
    expect($booking->fresh())->payment_status->toBe('partial')->deposit_secured_at->not->toBeNull();
    expect(app(BookingPaymentAccountingService::class)->summary($booking)['remaining_cents'])->toBe(500000);
    expect($service->prepare($booking->fresh(), 'balance', 'gotyme')->amount)->toBe(500000);
});

test('a late webhook for money paid before the deadline can still credit an unreleased booking', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $paidAt = $request->due_at->copy()->subMinute();
    $this->travelTo($request->due_at->copy()->addHour());
    $receipt = app(BookingPaymentAccountingService::class)->receipt($request, 'pay_before_deadline', 500000, $paidAt);
    expect($receipt->allocated)->toBeTrue();
    expect($booking->fresh()->deposit_secured_at)->not->toBeNull();
});

test('an uncertain provider outcome retains the inventory hold for review', function () {
    $booking = installmentBooking();
    app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $this->travel(3)->days();
    expect(app(BookingExpiryService::class)->expireIfDue($booking->fresh()))->toBeFalse();
    expect($booking->fresh())->status->toBe('approved')->balance_overdue_at->not->toBeNull();
});

test('a definitive Stripe expired session allows a new method without losing its history', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'stripe');
    $request->update(['provider_reference' => 'cs_expired']);
    Http::fake(['api.stripe.com/v1/checkout/sessions/cs_expired' => Http::response(['status' => 'expired', 'payment_status' => 'unpaid'])]);
    app(InstallmentPaymentService::class)->check($request);
    expect($request->fresh()->status)->toBe('expired');
    expect(app(InstallmentPaymentService::class)->prepare($booking->fresh(), 'deposit', 'paymongo')->id)->not->toBe($request->id);
    expect(BookingPaymentRequest::count())->toBe(2);
});

test('unconfigured PayMongo does not create a collection that blocks other methods', function () {
    $booking = installmentBooking();
    config(['services.qrph.secret_key' => '']);
    expect(fn () => app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo'))->toThrow(PaymentGatewayException::class);
    expect(BookingPaymentRequest::count())->toBe(0);
    expect(app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme')->gateway)->toBe('gotyme');
});

test('receipt posting and webhook dedupe roll back together when a database write fails', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    try {
        DB::transaction(function () use ($request) {
            app(BookingPaymentAccountingService::class)->receipt($request, 'pay_rollback', 500000);
            throw new RuntimeException('Simulate event ledger insertion failure.');
        });
    } catch (RuntimeException) {
    }
    expect(BookingPaymentTransaction::count())->toBe(0);
    expect($booking->fresh()->payment_status)->toBe('unpaid');
    app(BookingPaymentAccountingService::class)->receipt($request, 'pay_rollback', 500000);
    expect(BookingPaymentTransaction::count())->toBe(1);
});

test('staff can allocate a late receipt only after explicitly resolving its deadline', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $this->travel(3)->days();
    $receipt = app(BookingPaymentAccountingService::class)->receipt($request, 'pay_after_deadline', 500000);
    expect($receipt->allocated)->toBeFalse();
    $admin = settingsAdmin();
    $this->actingAs($admin, 'admin')->post(route('admin.bookings.payment-allocation', $booking->id), ['receipt_id' => $receipt->id, 'reason' => 'Verified genuine bank payment.'])->assertSessionHas('error');
    $this->post(route('admin.bookings.payment-deadline', $booking->id), ['deadline' => now()->addDay()->toIso8601String(), 'reason' => 'Accepted the delayed customer transfer.'])->assertSessionHasNoErrors();
    $this->post(route('admin.bookings.payment-allocation', $booking->id), ['receipt_id' => $receipt->id, 'reason' => 'Verified genuine bank payment.'])->assertSessionHas('success');
    expect($booking->fresh())->payment_status->toBe('partial')->deposit_secured_at->not->toBeNull();
    expect(BookingPaymentTransaction::count())->toBe(1);
});

test('financial reports count gross customer deposits by receipt date and subtract verified refunds', function () {
    $this->travelTo(Carbon::parse('2026-10-16 17:00:00', 'UTC'));
    DB::statement("SET LOCAL TIME ZONE 'Asia/Manila'");
    $this->app->instance('env', 'production');
    $booking = installmentBooking();
    $booking->forceFill(['created_at' => now()->subMonths(2)])->save();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $receipt = app(BookingPaymentAccountingService::class)->receipt($request, 'pay_report', 500000);
    $accounting = app(BookingPaymentAccountingService::class);
    expect($accounting->collectedBetween(now()->startOfDay(), now()->endOfDay()))->toBe(5000.0);
    expect($accounting->outstandingFor(Booking::query()->whereKey($booking->id)))->toBe(5000.0);
    $admin = settingsAdmin();
    $accounting->recordRefund($booking, $receipt, 100000, 'refund_report', $admin, 'Bank refund confirmed.');
    expect($accounting->collectedBetween(now()->startOfDay(), now()->endOfDay()))->toBe(4000.0);
    expect($accounting->collectionSeries(now()->startOfDay(), now()->endOfDay()))->toEqual(['2026-10-17' => 4000.0]);
    $this->actingAs($admin, 'admin')->get(route('admin.reports.index'))->assertOk()->assertSee('4,000.00');
});

test('a completed Stripe deposit webhook verifies its request and does not mark the entire booking paid', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'stripe');
    $request->update(['provider_reference' => 'cs_deposit_webhook']);
    $event = ['id' => 'evt_deposit_stripe', 'type' => 'checkout.session.completed', 'created' => now()->timestamp,
        'data' => ['object' => ['id' => 'cs_deposit_webhook', 'payment_status' => 'paid', 'client_reference_id' => $booking->booking_code,
            'amount_total' => 500000, 'currency' => 'php', 'livemode' => false, 'payment_intent' => 'pi_deposit_webhook',
            'metadata' => ['payment_request_uuid' => $request->uuid]]]];
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $t = now()->timestamp;
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, 'stripe_installment_signing')];
    $this->call('POST', '/webhook/payment/stripe', [], [], [], $headers, $body)->assertOk();
    $this->call('POST', '/webhook/payment/stripe', [], [], [], $headers, $body)->assertOk();
    expect($booking->fresh())->payment_status->toBe('partial')->deposit_secured_at->not->toBeNull();
    expect(BookingPaymentTransaction::count())->toBe(1);
});

test('a customer approval uses the saved payment window and snapshots the policy', function () {
    config(['system_settings.payments.initial_window_hours' => 72]);
    $booking = installmentBooking();
    $booking->forceFill(['status' => 'pending', 'payment_plan_version' => null, 'payment_policy' => null])->save();
    $item = $booking->items->first();
    $this->actingAs(settingsAdmin(), 'admin')->post(route('admin.bookings.approve', $booking->id), ['items' => [$item->id => ['include' => 1, 'quantity' => 1]]])->assertRedirect()->assertSessionHasNoErrors();
    expect($booking->fresh())->status->toBe('approved')->payment_plan_version->toBe(2);
    expect($booking->fresh()->payment_deadline->diffInHours(now(), true))->toBeGreaterThan(71);
    expect($booking->fresh()->payment_policy['initial_window_hours'])->toBe(72);
});

test('disabling new deposit offers leaves an existing deposit customer able to pay their balance', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    app(BookingPaymentAccountingService::class)->receipt($request, 'pay_existing_plan', 500000);
    config(['system_settings.payments.installments_enabled' => false]);
    expect(app(InstallmentPaymentService::class)->prepare($booking->fresh(), 'balance', 'gotyme')->amount)->toBe(500000);
    expect(app(BookingPaymentPolicy::class)->approvalData(Booking::factory()->approved()->create())['payment_plan_version'])->toBeNull();
});

test('settings and ledger administration render without exposing provider credentials', function () {
    $booking = installmentBooking();
    $admin = settingsAdmin();
    $this->actingAs($admin, 'admin')->get(route('admin.settings.index'))->assertOk()->assertSee('Booking &amp; payments', false)->assertSee('Initial payment window after reservation submission')->assertDontSee('sk_live_installment_fixture');
    $this->get(route('admin.bookings.show', $booking->id))->assertOk()->assertSee('Extend payment deadline')->assertSee('Record money already received offline');
});

test('an existing near term booking explains its cutoff without blaming flexible activities or changing saved deadlines', function () {
    config(['system_settings.payments.balance_lead_days' => 7]);
    $this->travelTo(Carbon::parse('2026-10-16 21:12:07', 'Asia/Manila'));
    $booking = installmentBooking('8500.00', 7);
    BookingItem::factory()->create(['booking_id' => $booking->id, 'item_title' => 'Party Yacht', 'item_type' => 'activity', 'check_in_date' => null, 'check_out_date' => null, 'availability_status' => 'available']);
    BookingItem::factory()->create(['booking_id' => $booking->id, 'item_title' => 'Zipline', 'item_type' => 'activity', 'check_in_date' => null, 'check_out_date' => null, 'availability_status' => 'available']);
    $policy = $booking->payment_policy;
    unset($policy['deposit_explanation']);
    $policy['service_start'] = null;
    $booking->forceFill(['payment_policy' => $policy, 'payment_deadline' => now()->addDays(5)])->save();
    $deadline = $booking->payment_deadline->toIso8601String();
    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))->assertOk()
        ->assertDontSee('services have no scheduled dates')->assertSee('Oct 16, 2026 9:00 AM')->assertSee('7-day balance rule')
        ->assertDontSee('Pay 50% to reserve');
    $this->get(route('booking.show', $booking->booking_code))->assertOk()
        ->assertSeeInOrder(['Your booking is approved', 'Booking payment'])->assertSee('Continue to full payment');
    expect($booking->fresh()->payment_deadline->toIso8601String())->toBe($deadline);
    expect($booking->fresh()->payment_policy)->toBe($policy);
});

test('eligible 8500 bookings offer a 4250 deposit and a separate balance deadline', function () {
    $booking = installmentBooking('8500.00', 30);
    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))->assertOk()
        ->assertSee('Pay 50% to reserve')->assertSee('4,250.00')->assertSee('Remaining balance due');
    expect(app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme')->amount)->toBe(425000);
    expect($booking->balance_due_at->gt($booking->payment_deadline))->toBeTrue();
});

test('approval deadline inputs are shown only before approval and extensions identify the active stage', function () {
    $booking = installmentBooking();
    $this->actingAs(settingsAdmin(), 'admin')->get(route('admin.bookings.show', $booking->id))->assertOk()
        ->assertDontSee('id="approval-payment-deadline"', false)->assertSee('Extend payment deadline after approval')->assertSee('New initial payment deadline')->assertDontSee('Mark as Paid');
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');
    app(BookingPaymentAccountingService::class)->receipt($request, 'bank_stage_labels', 500000);
    $this->get(route('admin.bookings.show', $booking->id))->assertOk()->assertSee('New remaining-balance deadline');
    $pending = Booking::factory()->create();
    $this->get(route('admin.bookings.show', $pending->id))->assertOk()->assertSee('Set the initial deadline when approving')->assertSee('Testing automatically enabled');
});

test('sandbox QR can pay a deposit and balance on an explicitly approved local test booking', function () {
    config(['services.stripe.secret_key' => 'sk_test_clarity_fixture']);
    $booking = installmentBooking('8500.00', 30);
    $booking->forceFill(['status' => 'pending', 'payment_plan_version' => null])->save();
    $item = $booking->items->first();
    $this->actingAs(settingsAdmin(), 'admin')->post(route('admin.bookings.approve', $booking->id), [
        'payment_environment' => 'test', 'items' => [$item->id => ['include' => 1, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();
    expect($booking->fresh()->payment_environment)->toBe('test');
    $options = app(InstallmentPaymentService::class)->gatewayOptions($booking->fresh());
    expect($options['qrph']['enabled'])->toBeTrue()->and($options['stripe']['enabled'])->toBeTrue()
        ->and($options['paymongo']['enabled'])->toBeTrue()->and($options['gotyme']['enabled'])->toBeTrue();
    installmentHttpFake();
    $this->actingAs($booking->user)->post(route('booking.installment.store', $booking->booking_code), ['purpose' => 'deposit', 'gateway' => 'qrph'])->assertSessionHasNoErrors();
    $attempt = QrphPaymentAttempt::firstOrFail();
    expect($attempt->amount)->toBe(425000)->and($attempt->livemode)->toBeFalse();
    [$body, $headers] = confirmInstallmentQr($attempt, 'evt_sandbox_deposit');
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($booking->fresh()->deposit_secured_at)->not->toBeNull();
    $this->post(route('booking.installment.store', $booking->booking_code), ['purpose' => 'balance', 'gateway' => 'qrph'])->assertSessionHasNoErrors();
    [$body, $headers] = confirmInstallmentQr(QrphPaymentAttempt::latest('id')->firstOrFail(), 'evt_sandbox_balance');
    $this->call('POST', '/webhook/payment/qrph', [], [], [], $headers, $body)->assertOk();
    expect($booking->fresh()->payment_status)->toBe('paid');
    expect(app(BookingPaymentAccountingService::class)->collectedBetween(now()->startOfDay(), now()->endOfDay()))->toBe(0.0);
});

test('Stripe test keys are clearly disabled on live bookings and cannot be submitted as real money', function () {
    $this->app->instance('env', 'production');
    config(['services.stripe.secret_key' => 'sk_test_clarity_fixture']);
    $booking = installmentBooking();
    $options = app(InstallmentPaymentService::class)->gatewayOptions($booking);
    expect($options['stripe']['enabled'])->toBeFalse()->and($options['qrph']['enabled'])->toBeFalse();
    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))->assertOk()
        ->assertSee('Stripe Card — Live')->assertSee('Configure credentials for this deployment');
    expect(fn () => app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'stripe'))->toThrow(PaymentGatewayException::class);
    expect(BookingPaymentRequest::count())->toBe(0);
});

test('production rejects sandbox designation even if an admin forges the approval request', function () {
    $booking = installmentBooking();
    $booking->forceFill(['status' => 'pending', 'payment_plan_version' => null])->save();
    $this->app->instance('env', 'production');
    $this->actingAs(settingsAdmin(), 'admin')->withSession(['_token' => 'sandbox-approval-fixture'])->post(route('admin.bookings.approve', $booking->id), ['_token' => 'sandbox-approval-fixture', 'payment_environment' => 'test'])->assertSessionHasErrors('payment_environment');
    expect($booking->fresh()->status)->toBe('pending');
});

test('an initial deadline override is honored when new deposits are disabled', function () {
    config(['system_settings.payments.installments_enabled' => false]);
    $deadline = now()->addHours(12);
    $data = app(BookingPaymentPolicy::class)->approvalData(Booking::factory()->approved()->create(), $deadline->toIso8601String());
    expect($data['payment_deadline']->equalTo($deadline->startOfSecond()))->toBeTrue();
});

test('refunding an unallocated receipt resolves its review without reducing existing reservation credit', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'paymongo');
    $this->travel(3)->days();
    $accounting = app(BookingPaymentAccountingService::class);
    $receipt = $accounting->receipt($request, 'pay_unallocated_refund', 500000);
    expect($receipt->allocated)->toBeFalse();
    $accounting->recordRefund($booking, $receipt, 500000, 'refund_unallocated', settingsAdmin(), 'Refund confirmed in the payment provider.');
    expect($request->fresh()->status)->toBe('cancelled');
    expect($booking->fresh())->payment_status->toBe('unpaid')->balance_overdue_at->toBeNull();
    expect($accounting->summary($booking)['verified_cents'])->toBe(0);
});

test('direct manual payment URLs require selecting the current installment first', function () {
    $booking = installmentBooking();
    $this->actingAs($booking->user)->get(route('booking.pay.gotyme.show', $booking->booking_code))->assertRedirect(route('booking.pay', $booking->booking_code));
    expect(BookingPaymentRequest::count())->toBe(0);
    expect($booking->fresh()->gateway)->not->toBe('gotyme');
});

test('resuming a stale manual request cannot reopen an already verified deposit', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');
    app(BookingPaymentAccountingService::class)->receipt($request, 'bank_already_verified', 500000);
    expect(fn () => app(InstallmentPaymentService::class)->start($request))->toThrow(PaymentGatewayException::class);
    expect($request->fresh()->status)->toBe('succeeded');
});

test('a delayed due-soon notice is discarded after its deadline becomes overdue', function () {
    $booking = installmentBooking();
    $request = app(InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');
    $receipt = app(BookingPaymentAccountingService::class)->receipt($request, 'bank_notice_deadline', 500000);
    $booking->refresh();
    DB::table('booking_payment_notices')->insert([
        'booking_id' => $booking->id, 'kind' => 'balance_due',
        'dedupe_key' => $booking->id.'-balance_due-'.hash('sha256', $booking->balance_due_at->toIso8601String()),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->travelTo($booking->balance_due_at->copy()->addMinute());
    $this->artisan('payments:process')->assertSuccessful();
    Notification::assertNotSentTo($booking->user, BookingPaymentDue::class, fn ($notice) => $notice->kind === 'balance_due');
});

test('independent concurrent processes reuse one collection and post one receipt on PostgreSQL', function () {
    expect(app()->environment('testing'))->toBeTrue();
    $connectionName = 'installment_concurrency';
    config(['database.connections.'.$connectionName => config('database.connections.'.config('database.default'))]);
    $connection = DB::connection($connectionName);
    expect($connection->getDatabaseName())->toBe(DB::connection()->getDatabaseName());
    $booking = Booking::factory()->approved()->make([
        'user_id' => null, 'net_amount' => '10000.00', 'payment_plan_version' => 2,
        'payment_environment' => 'test', 'deposit_required_cents' => 500000,
        'balance_due_at' => now()->addDays(20),
        'payment_policy' => ['quote_amount' => 1000000, 'deposit_eligible' => true],
    ]);
    $booking->setConnection($connectionName)->save();
    $worker = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (! $app->environment('testing') || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== $argv[4]) {
        throw new RuntimeException('Concurrency worker must use the selected test database.');
    }
    config(['cache.default' => 'array']);
    while (microtime(true) < (float) $argv[3]) { usleep(1000); }
    if ($argv[1] === 'prepare') {
        $booking = App\Models\Booking::findOrFail($argv[2]);
        $request = app(App\Services\Payment\InstallmentPaymentService::class)->prepare($booking, 'deposit', 'gotyme');
        echo $request->id;
    } else {
        $request = App\Models\BookingPaymentRequest::findOrFail($argv[2]);
        $receipt = app(App\Services\Payment\BookingPaymentAccountingService::class)->receipt($request, 'bank_concurrent_'.$request->uuid, 500000);
        echo $receipt->id;
    }
    PHP;
    $runPair = function (string $operation, int $id) use ($worker, $connection): array {
        $startAt = (string) (microtime(true) + 2);
        $processes = [];
        try {
            foreach (range(1, 2) as $unused) {
                $process = new Process([PHP_BINARY, '-r', $worker, $operation, (string) $id, $startAt, $connection->getDatabaseName()], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30)->start();
                $processes[] = $process;
            }

            return array_map(function ($process): string {
                $process->wait();
                expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

                return trim($process->getOutput());
            }, $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    };
    try {
        $requestIds = $runPair('prepare', $booking->id);
        expect($requestIds[0])->toBe($requestIds[1]);
        expect($connection->table('booking_payment_requests')->where('booking_id', $booking->id)->count())->toBe(1);
        $receiptIds = $runPair('receipt', (int) $requestIds[0]);
        expect($receiptIds[0])->toBe($receiptIds[1]);
        expect($connection->table('booking_payment_transactions')->where('booking_id', $booking->id)->count())->toBe(1);
        expect($booking->fresh())->payment_status->toBe('partial')->deposit_secured_at->not->toBeNull();
    } finally {
        $connection->table('booking_payment_notices')->where('booking_id', $booking->id)->delete();
        $connection->table('booking_payment_transactions')->where('booking_id', $booking->id)->delete();
        $connection->table('booking_payment_requests')->where('booking_id', $booking->id)->delete();
        $booking->delete();
        DB::purge($connectionName);
    }
});
