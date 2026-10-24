<?php

use App\Jobs\SendTripJourneyMail;
use App\Mail\TripJourneyMail;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPaymentTransaction;
use App\Models\CartItem;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\TripItinerary;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use App\Services\AccountDeletionService;
use App\Services\TripJourneyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-13 12:00', 'Asia/Manila'));
    $this->user = onboardedUser();
    $this->room = RoomType::factory()->create();
    $this->booking = Booking::factory()->paid()->create(['user_id' => $this->user->id, 'trip_itineraries' => [[
        'title' => 'Island days', 'days' => [['day' => 1, 'date' => '2026-10-14', 'hotel' => ['name' => 'Our hotel'], 'stops' => []]], 'booked_plan_item_ids' => [],
    ]]]);
    $this->item = BookingItem::factory()->create(['booking_id' => $this->booking->id, 'item_id' => $this->room->id, 'check_in_date' => '2026-10-14',
        'check_out_date' => '2026-10-16', 'availability_status' => 'available']);
});

it('queues one upcoming trip message and deduplicates repeated scheduler runs', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $service = app(TripJourneyService::class);
    expect($service->process())->toBe(1)->and($service->process())->toBe(0);
    Queue::assertPushed(SendTripJourneyMail::class, 1);
    expect(DB::table('trip_journey_messages')->first()->kind)->toBe('pre_trip');
});

it('checks cancellation and email preferences again when a queued message is delivered', function () {
    Queue::fake([SendTripJourneyMail::class]);
    Mail::fake();
    $service = app(TripJourneyService::class);
    $service->process();
    $message = DB::table('trip_journey_messages')->first();
    $this->booking->update(['status' => 'cancelled']);
    (new SendTripJourneyMail($message->id))->handle($service);
    Mail::assertNothingSent();
    expect(DB::table('trip_journey_messages')->value('status'))->toBe('suppressed');
    Queue::assertPushed(SendTripJourneyMail::class, 1);
});

it('suppresses an old reminder when stay dates are extended after queuing', function () {
    Queue::fake([SendTripJourneyMail::class]);
    Mail::fake();
    $service = app(TripJourneyService::class);
    $service->process();
    $message = DB::table('trip_journey_messages')->first();
    $this->item->update(['check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22']);
    (new SendTripJourneyMail($message->id))->handle($service);
    Mail::assertNothingSent();
    expect(DB::table('trip_journey_messages')->value('status'))->toBe('suppressed');
    Queue::assertPushed(SendTripJourneyMail::class, 1);
});

it('sends the stored message once with a stable provider idempotency key', function () {
    Queue::fake([SendTripJourneyMail::class]);
    Mail::fake();
    $service = app(TripJourneyService::class);
    $service->process();
    $message = DB::table('trip_journey_messages')->first();
    $job = new SendTripJourneyMail($message->id);
    $job->handle($service);
    $job->handle($service);
    Mail::assertSent(TripJourneyMail::class, fn ($mail) => $mail->hasTo($this->user->email) && $mail->headers()->text['Resend-Idempotency-Key'] === $message->dedupe_key);
    Mail::assertSentCount(1);
    Queue::assertPushed(SendTripJourneyMail::class, 1);
    expect(DB::table('trip_journey_messages')->value('status'))->toBe('sent');
});

it('does not queue abandoned plan advertising without marketing consent', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['status' => 'cancelled']);
    $plan = TripPlan::factory()->create(['user_id' => $this->user->id, 'updated_at' => now()->subDays(2)]);
    TripPlanItem::factory()->create(['trip_plan_id' => $plan->id]);
    $service = app(TripJourneyService::class);
    expect($service->process())->toBe(0);
    Queue::assertNothingPushed();
    $this->user->trip_marketing_enabled = true;
    $this->user->save();
    expect($service->process())->toBe(1)->and(DB::table('trip_journey_messages')->value('kind'))->toBe('continue');
    Queue::assertPushed(SendTripJourneyMail::class, 1);
});

it('requires fulfillment evidence and the grace period before automatic completion', function () {
    $this->item->update(['check_in_date' => '2026-10-09', 'check_out_date' => '2026-10-11']);
    $service = app(TripJourneyService::class);
    expect($service->completeIfDue($this->booking))->toBeFalse();
    $this->item->update(['fulfillment_status' => 'fulfilled', 'fulfilled_at' => now()->subDay()]);
    expect($service->completeIfDue($this->booking))->toBeTrue()->and($this->booking->fresh()->status)->toBe('completed');
    $this->booking->update(['status' => 'paid']);
    $this->item->update(['check_out_date' => '2026-10-13']);
    expect($service->completeIfDue($this->booking))->toBeFalse();
});

it('respects a trip extension and cancellation even when services were fulfilled', function () {
    $this->item->update(['check_in_date' => '2026-10-09', 'check_out_date' => '2026-10-11', 'fulfillment_status' => 'fulfilled']);
    $this->booking->update(['trip_end_override' => now()->addDay()]);
    $service = app(TripJourneyService::class);
    expect($service->completeIfDue($this->booking))->toBeFalse();
    $this->booking->update(['trip_end_override' => null, 'status' => 'cancellation_requested']);
    expect($service->completeIfDue($this->booking))->toBeFalse();
});

it('rejects review ids from another booking and excluded or unfulfilled services', function () {
    $this->actingAs($this->user);
    $this->booking->update(['status' => 'completed']);
    $foreign = BookingItem::factory()->create();
    $payload = ['booking_id' => $this->booking->id, 'rating' => 5, 'comment' => 'Lovely hotel and a relaxing trip.'];
    $this->postJson(route('reviews.store'), $payload + ['booking_item_id' => $foreign->id])->assertUnprocessable();
    $this->postJson(route('reviews.store'), $payload + ['booking_item_id' => $this->item->id])->assertUnprocessable();
    $this->item->update(['fulfillment_status' => 'fulfilled', 'availability_status' => 'unavailable']);
    $this->postJson(route('reviews.store'), $payload + ['booking_item_id' => $this->item->id])->assertUnprocessable();
});

it('records admin fulfillment and extension evidence while rejecting foreign item ids', function () {
    $admin = AdminModel::create(['name' => 'Fulfillment Admin', 'email' => 'fulfillment@example.com', 'password' => 'password']);
    $this->actingAs($admin, 'admin');
    $this->patch(route('admin.bookings.trip-fulfillment', $this->booking), ['items' => [$this->item->id => 'fulfilled'], 'evidence_note' => 'Hotel confirmed guest check-out.', 'trip_end_override' => '2026-10-18T12:00'])->assertRedirect();
    expect($this->item->fresh()->fulfillment_status)->toBe('fulfilled')->and($this->item->fresh()->fulfillment_admin_id)->toBe($admin->id)
        ->and($this->booking->fresh()->trip_end_override->timezone('Asia/Manila')->format('Y-m-d H:i'))->toBe('2026-10-18 12:00');
    $foreign = BookingItem::factory()->create();
    $this->patchJson(route('admin.bookings.trip-fulfillment', $this->booking), ['items' => [$foreign->id => 'fulfilled'], 'evidence_note' => 'Invalid item should be rejected.'])->assertUnprocessable();
    expect($foreign->fresh()->fulfillment_status)->toBe('pending');
});

it('lets the owner change marketing preferences without changing another account', function () {
    $this->actingAs($this->user)->patchJson(route('trip-planner.preferences'), ['reminders' => false, 'marketing' => true])->assertOk();
    expect($this->user->fresh()->trip_reminders_enabled)->toBeFalse()->and($this->user->fresh()->trip_marketing_enabled)->toBeTrue();
});

it('omits excluded activities from reminders even if they remain in the historical plan', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $snapshot = $this->booking->trip_itineraries;
    $snapshot[0]['booked_plan_item_ids'] = [123];
    $snapshot[0]['days'][0]['stops'] = [['plan_item_id' => 123, 'type' => 'activity', 'name' => 'Excluded sailing trip', 'start' => '10:00']];
    $this->booking->update(['trip_itineraries' => $snapshot]);
    BookingItem::factory()->create(['booking_id' => $this->booking->id, 'item_type' => 'activity', 'availability_status' => 'unavailable', 'item_snapshot' => ['trip_plan_item_id' => 123]]);
    app(TripJourneyService::class)->process();
    $payload = json_decode(DB::table('trip_journey_messages')->value('payload'), true);
    expect($payload['schedule'][0]['visits'])->toBeEmpty();
    Queue::assertPushed(SendTripJourneyMail::class, 1);
});

it('queues and delivers each journey email to an account without a verified email', function (string $kind) {
    Queue::fake([SendTripJourneyMail::class]);
    Mail::fake();
    $this->user->forceFill(['email_verified_at' => null])->save();
    $service = app(TripJourneyService::class);

    expect($service->process())->toBe(1);
    $message = DB::table('trip_journey_messages')->sole();
    (new SendTripJourneyMail($message->id))->handle($service);

    expect($message->kind)->toBe($kind)->and($this->user->fresh()->email_verified_at)->toBeNull();
    Mail::assertSent(TripJourneyMail::class, fn ($mail) => $mail->hasTo($this->user->email));
})->with([
    'pre-trip reminder' => fn () => 'pre_trip',
    'departure reminder' => function () {
        $this->item->update(['check_in_date' => '2026-10-12', 'check_out_date' => '2026-10-13']);

        return 'departure';
    },
    'review invitation' => function () {
        $this->booking->update(['status' => 'completed']);
        $this->item->update(['check_in_date' => '2026-10-09', 'check_out_date' => '2026-10-11', 'fulfillment_status' => 'fulfilled']);

        return 'review';
    },
    'return-trip suggestion' => function () {
        $this->booking->update(['status' => 'completed']);
        $this->item->update(['check_in_date' => '2026-09-08', 'check_out_date' => '2026-09-10', 'fulfillment_status' => 'fulfilled']);
        $this->user->forceFill(['trip_marketing_enabled' => true])->save();

        return 'repeat';
    },
    'saved-plan follow-up' => function () {
        $this->booking->update(['status' => 'cancelled']);
        $this->user->forceFill(['trip_marketing_enabled' => true])->save();
        $plan = TripPlan::factory()->create(['user_id' => $this->user->id, 'updated_at' => now()->subDays(2)]);
        TripPlanItem::factory()->create(['trip_plan_id' => $plan->id]);

        return 'continue';
    },
]);

it('lists every booked itinerary in a trip reminder', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['trip_itineraries' => [
        ['title' => 'Boracay days', 'days' => [['day' => 1, 'date' => '2026-10-14', 'stops' => []]], 'booked_plan_item_ids' => []],
        ['title' => 'El Nido days', 'days' => [['day' => 1, 'date' => '2026-10-15', 'stops' => []]], 'booked_plan_item_ids' => []],
    ]]);

    app(TripJourneyService::class)->process();

    $labels = array_column(json_decode(DB::table('trip_journey_messages')->value('payload'), true)['schedule'], 'label');
    expect($labels)->toBe(['Boracay days · Day 1 · 2026-10-14', 'El Nido days · Day 1 · 2026-10-15']);
});

it('links the saved-plan follow-up to that plan', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['status' => 'cancelled']);
    $this->user->forceFill(['trip_marketing_enabled' => true])->save();
    $plan = TripPlan::factory()->create(['user_id' => $this->user->id, 'updated_at' => now()->subDays(2)]);
    TripPlanItem::factory()->create(['trip_plan_id' => $plan->id]);

    app(TripJourneyService::class)->process();

    expect(json_decode(DB::table('trip_journey_messages')->value('payload'), true)['url'])->toBe(route('trip-planner.index', ['plan' => $plan->id]));
});

it('skips the review invitation once every service has been reviewed', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['status' => 'completed']);
    $this->item->update(['check_in_date' => '2026-10-09', 'check_out_date' => '2026-10-11', 'fulfillment_status' => 'fulfilled']);
    Review::create(['booking_id' => $this->booking->id, 'booking_item_id' => $this->item->id, 'user_id' => $this->user->id,
        'reviewable_type' => (new RoomType)->getMorphClass(), 'reviewable_id' => $this->room->id, 'rating' => 5, 'comment' => 'A lovely, restful stay by the beach.']);

    expect(app(TripJourneyService::class)->process())->toBe(0);
    Queue::assertNothingPushed();
});

it('keeps trip reminders and marketing follow-ups on separate 20-hour limits', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $service = app(TripJourneyService::class);
    $this->booking->update(['status' => 'cancelled']);
    $this->user->forceFill(['trip_marketing_enabled' => true])->save();
    $plan = TripPlan::factory()->create(['user_id' => $this->user->id, 'updated_at' => now()->subDays(2)]);
    TripPlanItem::factory()->create(['trip_plan_id' => $plan->id]);
    expect($service->process())->toBe(1);

    $this->booking->update(['status' => 'paid']);

    expect($service->process())->toBe(1)
        ->and(DB::table('trip_journey_messages')->orderBy('id')->pluck('kind')->all())->toBe(['continue', 'pre_trip']);
});

it('lets account cleanup remove a planner and cart without foreign key conflicts', function () {
    $plan = TripPlan::factory()->create(['user_id' => $this->user->id]);
    $itinerary = TripItinerary::factory()->create(['trip_plan_id' => $plan->id]);
    CartItem::create(['user_id' => $this->user->id, 'item_type' => 'room', 'item_id' => $this->room->id,
        'quantity' => 1, 'selected_pax' => 2, 'trip_itinerary_id' => $itinerary->id]);
    $service = app(AccountDeletionService::class);
    $service->requestDeletion($this->user);
    $this->travel(6)->days();
    expect($service->deleteIfExpired($this->user->id))->toBeTrue()->and(TripPlan::whereKey($plan->id)->exists())->toBeFalse();
});

function journeyPayloads(): array
{
    return DB::table('trip_journey_messages')->orderBy('id')->get()->map(fn ($row) => ['kind' => $row->kind] + json_decode($row->payload, true))->all();
}

it('reminds a dated catalog booking that has no saved itinerary', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['trip_itineraries' => null]);

    expect(app(TripJourneyService::class)->process())->toBe(1);

    $payload = journeyPayloads()[0];
    expect($payload['kind'])->toBe('pre_trip')->and($payload['schedule'])->toBe([])
        ->and($payload['services'][0]['name'])->toBe($this->item->item_title)
        ->and($payload['url'])->toBe(route('booking.show', $this->booking->booking_code));
});

it('reminds a Lucky booking and lists its undated activities as date to be arranged', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['trip_itineraries' => null]);
    $this->item->update(['item_snapshot' => ['lucky_group_id' => 'lucky-1']]);
    BookingItem::factory()->create(['booking_id' => $this->booking->id, 'item_type' => 'activity', 'item_title' => 'Island hopping', 'availability_status' => 'available', 'item_snapshot' => ['lucky_group_id' => 'lucky-1']]);

    expect(app(TripJourneyService::class)->process())->toBe(1)
        ->and(journeyPayloads()[0]['undated'])->toBe(['Island hopping']);
});

it('sends an admin guest booking its service reminders at the contact email without an account link', function () {
    Queue::fake([SendTripJourneyMail::class]);
    Mail::fake();
    $admin = AdminModel::create(['name' => 'Desk Admin', 'email' => 'desk@example.com', 'password' => 'password']);
    $this->booking->update(['user_id' => null, 'booked_by_admin_id' => $admin->id, 'booking_source' => 'admin_walk_in', 'contact_email' => 'walkin@example.com']);
    $service = app(TripJourneyService::class);

    expect($service->process())->toBe(1);
    $message = DB::table('trip_journey_messages')->sole();
    $payload = json_decode($message->payload, true);
    expect($message->user_id)->toBeNull()->and($message->recipient_email)->toBe('walkin@example.com')
        ->and($payload['url'])->toBeNull()->and($payload['preferences_url'])->toBeNull()->and($payload['support'])->not->toBeEmpty();

    (new SendTripJourneyMail($message->id))->handle($service);
    Mail::assertSent(TripJourneyMail::class, fn ($mail) => $mail->hasTo('walkin@example.com'));

    DB::table('trip_journey_messages')->delete();
    $this->booking->update(['status' => 'completed']);
    $this->item->update(['check_in_date' => '2026-10-09', 'check_out_date' => '2026-10-11', 'fulfillment_status' => 'fulfilled']);
    expect($service->process())->toBe(0);
});

it('does not write to a booking that lost its account unless an admin created it for a guest', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['user_id' => null, 'booking_source' => 'customer_web']);

    expect(app(TripJourneyService::class)->process())->toBe(0);
    Queue::assertNothingPushed();
});

it('reminds every booking of the same traveler that starts on the same day', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $second = Booking::factory()->paid()->create(['user_id' => $this->user->id]);
    BookingItem::factory()->create(['booking_id' => $second->id, 'item_type' => 'activity', 'check_in_date' => '2026-10-14', 'availability_status' => 'available']);

    expect(app(TripJourneyService::class)->process())->toBe(2)
        ->and(DB::table('trip_journey_messages')->pluck('booking_id')->sort()->values()->all())->toBe([$this->booking->id, $second->id]);
});

it('reminds each trip window of one booking when that window is due', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $later = BookingItem::factory()->create(['booking_id' => $this->booking->id, 'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-03', 'availability_status' => 'available']);
    $service = app(TripJourneyService::class);

    expect($service->process())->toBe(1)->and(DB::table('trip_journey_messages')->value('service_group'))->toBe('trip:'.$this->item->id);

    $this->travelTo(Carbon::parse('2026-10-31 12:00', 'Asia/Manila'));
    expect($service->process())->toBe(1)
        ->and(DB::table('trip_journey_messages')->orderByDesc('id')->value('service_group'))->toBe('trip:'.$later->id);
});

it('uses activity wording when an activity-only trip is wrapping up', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['trip_itineraries' => null]);
    $this->item->update(['item_type' => 'activity', 'check_in_date' => '2026-10-13', 'check_out_date' => null]);

    app(TripJourneyService::class)->process();

    $payload = journeyPayloads()[0];
    expect($payload['kind'])->toBe('departure')->and($payload['title'])->toBe('Your trip is wrapping up')
        ->and($payload['message'])->not->toContain('checkout');
});

it('describes an outstanding or overdue balance and skips unsecured reservations', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['status' => 'approved', 'payment_status' => 'unpaid', 'payment_plan_version' => 2, 'net_amount' => 6000]);
    $service = app(TripJourneyService::class);
    expect($service->process())->toBe(0);

    $this->booking->update(['payment_status' => 'partial', 'deposit_secured_at' => now()]);
    BookingPaymentTransaction::factory()->create(['booking_id' => $this->booking->id, 'amount' => 300000]);
    expect($service->process())->toBe(1)->and(journeyPayloads()[0]['payment_notice'])->toContain('₱3,000.00 remains');

    DB::table('trip_journey_messages')->delete();
    $this->booking->update(['balance_overdue_at' => now()]);
    $service->process();
    expect(journeyPayloads()[0]['payment_notice'])->toContain('past due')->toContain('not fully confirmed');
});

it('asks an entirely undated secured booking to confirm dates once', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->booking->update(['trip_itineraries' => null, 'paid_at' => now()->subHour()]);
    $this->item->update(['item_type' => 'package', 'check_in_date' => null, 'check_out_date' => null]);
    $service = app(TripJourneyService::class);

    expect($service->process())->toBe(1)->and($service->process())->toBe(0)
        ->and(journeyPayloads()[0]['kind'])->toBe('date_confirmation')
        ->and($service->diagnostics()['undated'])->toBe([$this->booking->booking_code]);

    DB::table('trip_journey_messages')->delete();
    $this->booking->update(['paid_at' => now()->subDays(4)]);
    expect($service->process())->toBe(0);
});

it('does not repeat a reminder that was queued before trip windows were recorded', function () {
    Queue::fake([SendTripJourneyMail::class]);
    DB::table('trip_journey_messages')->insert(['user_id' => $this->user->id, 'booking_id' => $this->booking->id, 'dedupe_key' => 'booking:'.$this->booking->id.':pre_trip:legacy',
        'kind' => 'pre_trip', 'status' => 'sent', 'payload' => '{}', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

    expect(app(TripJourneyService::class)->process())->toBe(0);
});

it('marks an interrupted send uncertain but retries a failure before sending began', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $service = app(TripJourneyService::class);
    $service->process();
    $messageId = DB::table('trip_journey_messages')->value('id');

    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    expect(fn () => (new SendTripJourneyMail($messageId))->handle($service))->toThrow(RuntimeException::class);
    expect(DB::table('trip_journey_messages')->value('status'))->toBe('uncertain');

    DB::table('trip_journey_messages')->update(['status' => 'pending']);
    $this->mock(TripJourneyService::class, fn ($mock) => $mock->shouldReceive('deliveryDecision')->andThrow(new RuntimeException('Database unavailable')));
    expect(fn () => (new SendTripJourneyMail($messageId))->handle(app(TripJourneyService::class)))->toThrow(RuntimeException::class);
    expect(DB::table('trip_journey_messages')->value('status'))->toBe('pending');
});

it('automatically completes a fulfilled catalog booking after its grace period', function () {
    $this->booking->update(['trip_itineraries' => null]);
    $this->item->update(['check_in_date' => '2026-10-09', 'check_out_date' => '2026-10-11', 'fulfillment_status' => 'fulfilled']);
    BookingItem::factory()->create(['booking_id' => $this->booking->id, 'item_type' => 'activity', 'availability_status' => 'available', 'fulfillment_status' => 'fulfilled']);

    expect(app(TripJourneyService::class)->completeIfDue($this->booking))->toBeTrue()
        ->and($this->booking->fresh()->status)->toBe('completed');
});

it('keeps assessing a booking whose recorded extension runs past the reminder horizon', function () {
    Queue::fake([SendTripJourneyMail::class]);
    $this->item->update(['check_in_date' => '2026-08-01', 'check_out_date' => '2026-08-03', 'fulfillment_status' => 'fulfilled']);
    $this->booking->update(['paid_at' => now()->subMonths(3), 'trip_end_override' => Carbon::parse('2026-10-14 12:00', 'Asia/Manila')]);

    $service = app(TripJourneyService::class);
    $service->process();

    expect($service->diagnostics()['bookings'])->toBe(1)->and($this->booking->fresh()->status)->toBe('paid');
});
