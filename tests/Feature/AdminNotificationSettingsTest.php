<?php

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\CartItem;
use App\Models\ChatSession;
use App\Models\SystemSetting;
use App\Notifications\AdminOperationalAlert;
use App\Notifications\BookingCancellationRequested;
use App\Services\AdminNotificationService;
use App\Services\Payment\GoTymePaymentService;
use App\Services\Support\SupportQueueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

test('enabled alerts reach only selected admins after a transaction commits', function () {
    $selected = settingsAdmin();
    $other = settingsAdmin();
    $preferences = config('system_settings.notifications');
    $preferences['booking_requested'] = true;
    $preferences['recipient_mode'] = 'selected';
    $preferences['admin_ids'] = [$selected->id];
    SystemSetting::factory()->create(['section' => 'notifications', 'values' => $preferences]);
    $booking = Booking::factory()->create();
    Notification::fake();

    DB::transaction(function () use ($booking): void {
        app(AdminNotificationService::class)->send('booking_requested', $booking);
        Notification::assertNothingSent();
    });

    Notification::assertSentTo($selected, AdminOperationalAlert::class, fn ($alert, $channels) => $alert->type === 'booking_requested' && $channels === ['mail']);
    Notification::assertNotSentTo($other, AdminOperationalAlert::class);
});

test('rolled back operations never dispatch alerts', function () {
    settingsAdmin();
    SystemSetting::factory()->create(['section' => 'notifications', 'values' => array_merge(config('system_settings.notifications'), ['booking_requested' => true])]);
    $booking = Booking::factory()->create();
    Notification::fake();

    DB::beginTransaction();
    app(AdminNotificationService::class)->send('booking_requested', $booking);
    DB::rollBack();

    Notification::assertNothingSent();
});

test('new operational alerts are disabled until the admin enables them', function () {
    settingsAdmin();
    $booking = Booking::factory()->create();
    Notification::fake();
    app(AdminNotificationService::class)->send('booking_requested', $booking);
    app(AdminNotificationService::class)->send('payment_submitted', $booking);
    Notification::assertNothingSent();
});

test('reusing an existing support inquiry does not send duplicate alerts', function () {
    $admin = settingsAdmin();
    SystemSetting::factory()->create(['section' => 'notifications', 'values' => array_merge(config('system_settings.notifications'), ['support_requested' => true])]);
    $session = ChatSession::create(['session_token' => ChatSession::generateToken()]);
    Notification::fake();

    $queue = app(SupportQueueService::class);
    $queue->requestHandoff($session);
    $queue->requestHandoff($session);

    Notification::assertSentToTimes($admin, AdminOperationalAlert::class, 1);
    $this->assertDatabaseCount('support_inquiries', 1);
});

test('only a newly accepted manual payment submission sends an alert', function () {
    $admin = settingsAdmin();
    $booking = Booking::factory()->approved()->create();
    SystemSetting::factory()->create(['section' => 'notifications', 'values' => array_merge(config('system_settings.notifications'), ['payment_submitted' => true])]);
    Notification::fake();

    app(GoTymePaymentService::class)->submit($booking, 'GT-TEST-ALERT', '1000.00');
    expect(fn () => app(GoTymePaymentService::class)->submit($booking, 'GT-TEST-ALERT', '1000.00'))->toThrow(ValidationException::class);

    Notification::assertSentToTimes($admin, AdminOperationalAlert::class, 1);
    $this->assertDatabaseCount('booking_payments', 1);
});

test('cancellation preferences preserve database notifications for every admin', function () {
    $selected = settingsAdmin();
    $other = settingsAdmin();
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->create(['user_id' => $user->id]);
    SystemSetting::factory()->create(['section' => 'notifications', 'values' => array_merge(config('system_settings.notifications'), [
        'recipient_mode' => 'selected', 'admin_ids' => [$selected->id],
    ])]);
    Notification::fake();

    $this->actingAs($user)->post(route('booking.cancel', $booking->booking_code), ['reason' => 'Plans changed'])
        ->assertSessionHasNoErrors()->assertRedirect();

    Notification::assertSentTo([$selected, $other], BookingCancellationRequested::class, fn ($alert, $channels) => $channels === ['database']);
    Notification::assertSentToTimes($selected, BookingCancellationRequested::class, 1);
    Notification::assertSentTo($selected, AdminOperationalAlert::class);
    Notification::assertNotSentTo($other, AdminOperationalAlert::class);
});

test('customer checkout sends a new booking alert after persistence', function () {
    $admin = settingsAdmin();
    $user = onboardedUser();
    $activity = ActivityModel::factory()->create();
    CartItem::factory()->create(['user_id' => $user->id, 'item_type' => 'activity', 'item_id' => $activity->id]);
    SystemSetting::factory()->create(['section' => 'notifications', 'values' => array_merge(config('system_settings.notifications'), ['booking_requested' => true])]);
    Notification::fake();

    $this->actingAs($user)->postJson(route('checkout.process'), [
        'contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => '09123456789',
    ])->assertOk();

    $this->assertDatabaseCount('bookings', 1);
    Notification::assertSentTo($admin, AdminOperationalAlert::class, fn ($alert) => $alert->type === 'booking_requested');
});

test('mail dispatch failure does not roll back a new support inquiry', function () {
    settingsAdmin();
    SystemSetting::factory()->create(['section' => 'notifications', 'values' => array_merge(config('system_settings.notifications'), ['support_requested' => true])]);
    $session = ChatSession::create(['session_token' => ChatSession::generateToken()]);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Queue unavailable'));

    $inquiry = app(SupportQueueService::class)->requestHandoff($session);

    $this->assertDatabaseHas('support_inquiries', ['id' => $inquiry->id]);
});
