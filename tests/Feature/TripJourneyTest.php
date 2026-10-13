<?php

use App\Jobs\SendTripJourneyMail;
use App\Mail\TripJourneyMail;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
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
