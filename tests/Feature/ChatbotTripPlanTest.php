<?php

use App\Models\ActivityModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\TripPlan;
use App\Notifications\TripItineraryReady;
use App\Services\Chat\ChatbotTripPlanService;
use App\Services\GeminiService;
use App\Services\RoomAvailabilityService;
use App\Services\RoutingService;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 21));
    Http::preventStrayRequests();
    Notification::fake();
    $this->user = onboardedUser();
    $this->actingAs($this->user);
    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->activity = ActivityModel::factory()->create(['destination_id' => $this->destination->id, 'is_shown' => true]);
    $this->session = ChatSession::create(['session_token' => ChatSession::generateToken(), 'user_id' => $this->user->id]);
    $this->suggestion = app(ChatbotTripPlanService::class)->suggestion(['activities' => [['id' => $this->activity->id, 'activity_name' => $this->activity->activity_name, 'day' => 1]]],
        ['destination_id' => $this->destination->id, 'destination_name' => 'Boracay', 'days' => 3, 'pax' => 2]);
    $this->message = ChatMessage::create(['chat_session_id' => $this->session->id, 'sender' => 'bot', 'message' => 'Suggested itinerary',
        'context_data' => ['itinerary_suggestions' => [$this->suggestion['id'] => $this->suggestion]]]);
});

test('an undated suggestion saves once without weather routing basket or notification effects', function () {
    $this->mock(WeatherService::class)->shouldNotReceive('forecastForDestination');
    $this->mock(RoutingService::class)->shouldNotReceive('matrix');
    $first = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id'], 'title' => 'My flexible trip', 'user_id' => 999]);
    $first->assertCreated()->assertJsonPath('data.start_date', null)->assertJsonPath('data.status', 'draft')->assertJsonPath('data.source', 'chatbot');
    $second = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']]);
    $second->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
    $plan = TripPlan::findOrFail($first->json('data.id'));
    expect($plan->items)->toHaveCount(1)->and($plan->itineraries)->toHaveCount(1)->and($plan->user_id)->toBe($this->user->id);
    $this->assertDatabaseCount('cart_items', 0);
    Notification::assertNothingSent();
    Http::assertNothingSent();
    $this->getJson('/trip-planner/plans/'.$plan->id)->assertOk()->assertJsonPath('itinerary.snapshot.days.0.day', 1);
    $this->getJson('/trip-planner/chatbot-plans?suggestion_ids[]='.$this->suggestion['id'])->assertOk()->assertJsonPath('data.'.$this->suggestion['id'], $first->json('url'));
});

test('saved stays retain room details and dates while edits invalidate old quotes', function () {
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id, 'is_shown' => true]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'is_shown' => true]);
    $suggestion = app(ChatbotTripPlanService::class)->suggestion([
        'hotel' => ['id' => $hotel->id, 'name' => $hotel->hotel_name],
        'room' => ['id' => $room->id, 'room_name' => $room->room_name, 'max_occupancy' => 4, 'nightly_rate' => 2500, 'total' => 7500],
        'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-04', 'pax' => 2,
    ], ['destination_id' => $this->destination->id, 'destination_name' => 'Boracay']);
    $this->message->update(['context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);

    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id']])->assertCreated();
    $plan = TripPlan::findOrFail($saved->json('data.id'));
    $this->getJson('/trip-planner/plans/'.$plan->id)->assertOk()
        ->assertJsonPath('itinerary.snapshot.accommodation.room_id', $room->id)
        ->assertJsonPath('itinerary.snapshot.accommodation.total', 7500)
        ->assertJsonPath('itinerary.snapshot.days.3.date', '2026-11-04');
    $this->get('/trip-planner/plans/'.$plan->id.'/itineraries/'.$plan->itineraries()->latest('id')->first()->id.'/document?print=1')
        ->assertOk()->assertSee($hotel->hotel_name)->assertSee($room->room_name)->assertSee('3 nights')->assertSee('2026-11-04');
    $this->patchJson('/trip-planner/plans/'.$plan->id, ['revision' => 1, 'title' => 'Reviewed stay'])->assertOk()
        ->assertJsonPath('itinerary.snapshot.accommodation.total', null)
        ->assertJsonPath('itinerary.snapshot.accommodation.availability_status', 'not_checked');
    expect($plan->itineraries()->oldest('id')->first()->snapshot['accommodation']['total'])->toBe(7500);
});

test('an unavailable selection is retained as an explicitly unavailable draft entry', function () {
    $this->activity->update(['is_shown' => false]);
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
    $this->getJson('/trip-planner/plans/'.$saved->json('data.id'))->assertOk()->assertJsonPath('itinerary.snapshot.choices.0.available', false)->assertJsonCount(0, 'data.items');
});

test('editing a saved room uses its own stay dates quantity and travelers in the summary and export', function () {
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id, 'is_shown' => true]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'is_shown' => true]);
    $suggestion = app(ChatbotTripPlanService::class)->suggestion([
        'hotel' => ['id' => $hotel->id, 'name' => $hotel->hotel_name],
        'room' => ['id' => $room->id, 'room_name' => $room->room_name, 'max_occupancy' => 4, 'nightly_rate' => 2500, 'total' => 7500],
        'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-04', 'pax' => 2,
    ], ['destination_id' => $this->destination->id, 'destination_name' => 'Boracay']);
    $this->message->update(['context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id']])->assertCreated();
    $base = '/trip-planner/plans/'.$saved->json('data.id');

    $edited = $this->postJson($base.'/items', ['item_type' => 'room', 'item_id' => $room->id,
        'quantity' => 2, 'pax' => 3, 'check_in' => '2026-11-02', 'check_out' => '2026-11-04']);

    $edited->assertOk()->assertJsonPath('draft_itinerary.snapshot.accommodation.check_in_date', '2026-11-02')
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.check_out_date', '2026-11-04')
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.nights', 2)
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.quantity', 2)
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.pax', 3)
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.total', null)
        ->assertJsonPath('draft_itinerary.snapshot.duration_nights', 3);
    $this->get($base.'/itineraries/'.$edited->json('draft_itinerary.id').'/document?print=1')->assertOk()
        ->assertSee('Check-in: 2026-11-02')->assertSee('2 nights')->assertSee('2 room')->assertSee('3 travelers for this stay');
    $this->patchJson($base, ['revision' => $edited->json('revision'), 'title' => 'Shorter hotel stay'])->assertOk()
        ->assertJsonPath('itinerary.snapshot.accommodation.check_in_date', '2026-11-02')
        ->assertJsonPath('itinerary.snapshot.accommodation.quantity', 2);
    $plan = TripPlan::findOrFail($saved->json('data.id'));
    expect($plan->itineraries()->oldest('id')->first()->snapshot['accommodation']['total'])->toBe(7500);
});

test('adding a room rebuilds a missing stay summary for a dated chatbot draft', function (bool $replace) {
    $suggestion = $this->suggestion;
    $suggestion['start_date'] = '2026-11-01';
    $suggestion['end_date'] = '2026-11-04';
    $suggestion['duration_days'] = 4;
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id, 'is_shown' => true]);
    if ($replace) {
        $oldRoom = RoomType::factory()->create(['hotel_id' => $hotel->id, 'is_shown' => true]);
        $suggestion['choices'][] = ['type' => 'room', 'id' => $oldRoom->id, 'name' => $oldRoom->room_name, 'day' => null];
        $suggestion['accommodation'] = ['room_id' => $oldRoom->id, 'room_name' => $oldRoom->room_name];
    }
    $this->message->update(['context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id']])->assertCreated();
    $base = '/trip-planner/plans/'.$saved->json('data.id');
    if ($replace) {
        $plan = TripPlan::findOrFail($saved->json('data.id'));
        $item = $plan->items()->where('item_type', 'room')->firstOrFail();
        $this->deleteJson($base.'/items/'.$item->id)->assertOk()->assertJsonPath('draft_itinerary.snapshot.accommodation', null);
    }
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'is_shown' => true, 'max_occupancy' => 4]);

    $added = $this->postJson($base.'/items', ['item_type' => 'room', 'item_id' => $room->id,
        'pax' => 2, 'quantity' => 1, 'check_in' => '2026-11-01', 'check_out' => '2026-11-04']);

    $added->assertOk()->assertJsonPath('draft_itinerary.snapshot.accommodation.room_id', $room->id)
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.hotel_name', $hotel->hotel_name)
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.capacity', 4)
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.nights', 3)
        ->assertJsonPath('draft_itinerary.snapshot.accommodation.availability_status', 'not_checked');
    $this->getJson($base)->assertOk()->assertJsonPath('itinerary.snapshot.accommodation.room_name', $room->room_name);
    $room->update(['is_shown' => false]);
    $this->patchJson($base, ['revision' => $added->json('revision'), 'title' => 'Review room availability'])->assertOk()
        ->assertJsonPath('itinerary.snapshot.accommodation.availability_status', 'unavailable');
})->with(['new stay' => [false], 'replacement stay' => [true]]);

test('another account cannot save a conversation suggestion or read its saved plan', function () {
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
    $this->actingAs(onboardedUser());
    $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertNotFound();
    $this->getJson('/trip-planner/plans/'.$saved->json('data.id'))->assertNotFound();
    $this->getJson('/trip-planner/chatbot-plans?suggestion_ids[]='.$this->suggestion['id'])->assertOk()->assertJsonCount(0, 'data');
});

test('a guest saves a draft to its browser session and keeps it after signing in', function () {
    $guestSession = ChatSession::create(['session_token' => ChatSession::generateToken(), 'user_id' => null]);
    $suggestion = app(ChatbotTripPlanService::class)->suggestion(['activities' => [['id' => $this->activity->id, 'activity_name' => $this->activity->activity_name, 'day' => 1]]],
        ['destination_id' => $this->destination->id, 'destination_name' => 'Boracay', 'days' => 3, 'pax' => 2]);
    ChatMessage::create(['chat_session_id' => $guestSession->id, 'sender' => 'bot', 'message' => 'Suggested itinerary',
        'context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);
    auth()->forgetGuards();

    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id'], 'session_token' => $guestSession->session_token]);
    $saved->assertCreated()->assertJsonPath('url', null)->assertJsonPath('claimed', false);
    $plan = TripPlan::findOrFail($saved->json('data.id'));
    expect($plan->user_id)->toBeNull()->and($plan->session_token)->not->toBeNull()->and($plan->items)->toHaveCount(1);

    // Saving twice in the same browser session is idempotent, as for an account.
    $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id'], 'session_token' => $guestSession->session_token])
        ->assertOk()->assertJsonPath('data.id', $plan->id);
    $this->assertDatabaseCount('trip_plans', 1);

    // The guest sees a saved state without a planner link it cannot open.
    $this->getJson('/trip-planner/chatbot-plans?suggestion_ids[]='.$suggestion['id'])->assertOk()->assertJsonPath('data.'.$suggestion['id'], true);

    $this->actingAs($this->user);
    $this->get('/trip-planner')->assertOk();
    expect($plan->fresh()->user_id)->toBe($this->user->id)->and($plan->fresh()->session_token)->toBeNull();
    $this->getJson('/trip-planner/chatbot-plans?suggestion_ids[]='.$suggestion['id'])->assertOk()
        ->assertJsonPath('data.'.$suggestion['id'], route('trip-planner.index', ['plan' => $plan->id]));
    $this->getJson('/trip-planner/plans/'.$plan->id)->assertOk();
});

test('a guest cannot save a suggestion from a session it does not hold', function () {
    auth()->forgetGuards();
    // The suggestion in beforeEach belongs to an authenticated session.
    $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id'], 'session_token' => $this->session->session_token])->assertNotFound();
    $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertNotFound();
    $this->assertDatabaseCount('trip_plans', 0);
});

test('a guest plan is discarded at sign-in when the account already saved that suggestion', function () {
    $mine = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
    $guest = TripPlan::create(['user_id' => null, 'session_token' => 'guest-token', 'destination_id' => $this->destination->id,
        'title' => 'Guest copy', 'source' => 'chatbot', 'status' => 'draft', 'source_suggestion_uuid' => $this->suggestion['id'],
        'duration_days' => 3, 'pax' => 2, 'mode' => 'walking', 'revision' => 1]);
    expect(app(ChatbotTripPlanService::class)->claimGuestPlans($this->user, 'guest-token'))->toBe(0);
    expect(TripPlan::find($guest->id))->toBeNull();
    expect(TripPlan::find($mine->json('data.id'))->user_id)->toBe($this->user->id);
});

test('unclaimed guest plans are pruned after the retention window', function () {
    $fresh = TripPlan::create(['user_id' => null, 'session_token' => 'token-a', 'destination_id' => $this->destination->id,
        'title' => 'Recent guest draft', 'source' => 'chatbot', 'status' => 'draft', 'duration_days' => 3, 'pax' => 2, 'mode' => 'walking', 'revision' => 1]);
    $stale = TripPlan::create(['user_id' => null, 'session_token' => 'token-b', 'destination_id' => $this->destination->id,
        'title' => 'Abandoned guest draft', 'source' => 'chatbot', 'status' => 'draft', 'duration_days' => 3, 'pax' => 2, 'mode' => 'walking', 'revision' => 1]);
    $stale->forceFill(['updated_at' => now()->subDays(31)])->saveQuietly();
    $this->artisan('trips:prune-guest-plans')->assertSuccessful();
    expect(TripPlan::find($stale->id))->toBeNull()->and(TripPlan::find($fresh->id))->not->toBeNull();
});

test('unsupported suggestion versions fail without creating a plan', function () {
    $suggestion = $this->suggestion;
    $suggestion['version'] = 999;
    $this->message->update(['context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);
    $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id']])->assertUnprocessable();
    $this->assertDatabaseCount('trip_plans', 0);
});

test('drafts cannot generate a dated schedule or transfer to basket without dates', function () {
    $response = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
    $plan = TripPlan::findOrFail($response->json('data.id'));
    $this->postJson('/trip-planner/plans/'.$plan->id.'/generate')->assertUnprocessable()->assertJsonValidationErrors('dates');
    $this->postJson('/trip-planner/plans/'.$plan->id.'/itineraries/'.$plan->itineraries()->first()->id.'/cart')->assertUnprocessable();
    $this->assertDatabaseCount('cart_items', 0);
});

test('dates and choices can be edited with revisions and the original snapshot is retained', function () {
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
    $plan = TripPlan::findOrFail($saved->json('data.id'));
    $url = '/trip-planner/plans/'.$plan->id;
    $updated = $this->patchJson($url, ['revision' => 1, 'title' => 'Updated trip', 'start_date' => '2026-11-01', 'end_date' => '2026-11-04', 'pax' => 2]);
    $updated->assertOk()->assertJsonPath('data.title', 'Updated trip')->assertJsonPath('data.context.pax_assumed', false)->assertJsonPath('itinerary.snapshot.start_date', '2026-11-01');
    expect($plan->itineraries()->count())->toBe(2)->and($plan->itineraries()->oldest('id')->first()->snapshot['start_date'])->toBeNull();
    $this->patchJson($url, ['revision' => 1, 'title' => 'stale'])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->patchJson($url, ['revision' => $updated->json('data.revision'), 'remove_choice' => 'activity:'.$this->activity->id])->assertOk()->assertJsonCount(0, 'itinerary.snapshot.choices');
});

test('draft document and explicit email support undated plans', function () {
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
    $plan = TripPlan::findOrFail($saved->json('data.id'));
    $base = '/trip-planner/plans/'.$plan->id.'/itineraries/'.$plan->itineraries()->first()->id;
    $this->get($base.'/document?print=1')->assertOk()->assertSee('Dates to be decided')->assertSee('Draft');
    $this->postJson($base.'/email')->assertOk();
    Notification::assertSentTo($this->user, TripItineraryReady::class);
});

test('saved plan pagination does not expose another users plans', function () {
    TripPlan::factory()->count(21)->create(['user_id' => $this->user->id]);
    TripPlan::factory()->create(['user_id' => onboardedUser()->id, 'title' => 'Private trip']);
    $this->getJson('/trip-planner/plans?page=2')->assertOk()->assertJsonCount(1, 'data')->assertDontSee('Private trip');
});

test('dated suggestions retain supplied dates while saving without availability or forecast requests', function () {
    $suggestion = $this->suggestion;
    $suggestion['start_date'] = '2026-11-01';
    $suggestion['end_date'] = '2026-11-03';
    $this->message->update(['context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id']])->assertCreated()->assertJsonPath('data.status', 'draft');
    expect(TripPlan::findOrFail($saved->json('data.id'))->start_date->toDateString())->toBe('2026-11-01');
    Http::assertNothingSent();
});

test('an authenticated migrated guest conversation can save its original suggestion', function () {
    $this->session->update(['user_id' => null]);
    $this->getJson('/chat/history?session_token='.$this->session->session_token)->assertOk();
    $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
});

test('new dated choices update the saved draft and it can later become a scheduled itinerary', function () {
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $this->suggestion['id']])->assertCreated();
    $plan = TripPlan::findOrFail($saved->json('data.id'));
    $base = '/trip-planner/plans/'.$plan->id;
    $this->patchJson($base, ['revision' => 1, 'start_date' => '2026-11-01', 'end_date' => '2026-11-04'])->assertOk();
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id, 'latitude' => 11.96, 'longitude' => 121.927]);
    $added = $this->postJson($base.'/items', ['item_type' => 'hotel', 'item_id' => $hotel->id])->assertOk();
    expect(array_column($added->json('draft_itinerary.snapshot.choices'), 'name'))->toContain($hotel->hotel_name);
    $this->activity->update(['latitude' => 11.97, 'longitude' => 121.928, 'duration' => '1 Hour']);
    $this->partialMock(RoutingService::class, function ($mock) {
        $mock->shouldReceive('matrix')->andReturnUsing(function ($sources, $targets): array {
            $distances = $durations = [];
            foreach ($sources as $i => $source) {
                foreach ($targets as $j => $target) {
                    $distances[$i][$j] = $i === $j ? 0 : 1;
                    $durations[$i][$j] = $i === $j ? 0 : 600;
                }
            }

            return ['distances' => $distances, 'durations' => $durations, 'mode' => 'walking'];
        });
        $mock->shouldReceive('route')->andReturn(['status' => 'unavailable', 'geometry' => null]);
    });
    $this->partialMock(WeatherService::class, fn ($mock) => $mock->shouldReceive('forecastForDestination')->andReturn(null));
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateTripReport')->andReturn(['A provisional schedule.']));
    $this->postJson($base.'/generate')->assertOk()->assertJsonStructure(['data' => ['snapshot' => ['days', 'unscheduled']]]);
    expect($plan->fresh()->status)->toBe('dated')->and($plan->itineraries()->oldest('id')->first()->snapshot['kind'])->toBe('chatbot_draft');
});

test('room availability failure rolls back an attempted draft date update', function () {
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'is_shown' => true, 'total_rooms' => 0]);
    $suggestion = $this->suggestion;
    $suggestion['choices'][] = ['type' => 'room', 'id' => $room->id, 'name' => $room->room_name, 'day' => null];
    $this->message->update(['context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);
    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id']])->assertCreated();
    $plan = TripPlan::findOrFail($saved->json('data.id'));
    $this->mock(RoomAvailabilityService::class)->shouldReceive('check')->andReturn(['remaining' => 0, 'available' => false]);
    $this->patchJson('/trip-planner/plans/'.$plan->id, ['revision' => 1, 'start_date' => '2026-11-01', 'end_date' => '2026-11-03'])->assertUnprocessable();
    expect($plan->fresh()->revision)->toBe(1)->and($plan->fresh()->start_date)->toBeNull()->and($plan->itineraries()->count())->toBe(1);
});
