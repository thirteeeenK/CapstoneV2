<?php

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\Chat\IntentRouter;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.chatbot.planner_enabled' => false, 'services.chatbot.conversational_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]]),
    ]);
    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create(['hotel_name' => 'Palm Beach Resort', 'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'room_name' => 'Palm Deluxe Suite', 'base_price' => 2500, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $this->activity = ActivityModel::factory()->create(['activity_name' => 'Parasailing Adventure', 'destination_id' => $this->destination->id, 'is_shown' => true, 'rate' => '900', 'embedding' => unitVectorString(0)]);
});

test('returns separate evidence outcomes and cards using one composition call', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities for 2 pax']);
    $response->assertOk()->assertJsonPath('schema_version', 2)->assertJsonCount(2, 'sections');
    expect($response->json('sections.0.data.retrieved_hotels.0.id'))->toBe($this->hotel->id)
        ->and($response->json('sections.1.data.retrieved_activities.0.id'))->toBe($this->activity->id);
    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'generateContent')))->toHaveCount(1);
    $session = ChatSession::where('session_token', $response->json('session_token'))->firstOrFail();
    expect($session->messages()->count())->toBe(2)
        ->and($session->metadata['conversation_v2']['groups'])->toHaveCount(2);
});

test('a typed ordinal resolves against its own result group', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $response = $this->postJson('/chat', ['message' => 'tell me about the first activity', 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('sections.0.entity.type', 'activity')
        ->assertJsonPath('sections.0.entity.id', $this->activity->id)
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('retrieved_hotels'))->toBeNull();
});

test('an ambiguous ordinal asks which category the user means', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $response = $this->postJson('/chat', ['message' => 'the second one', 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('sections.0.outcome.status', 'needs_clarification');
});

test('one missing task is explicit and does not remove verified recommendations', function () {
    $this->activity->delete();
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $response->assertOk()->assertJsonPath('retrieval_outcome.status', 'partial')
        ->assertJsonPath('sections.0.outcome.status', 'matched')
        ->assertJsonPath('sections.1.outcome.status', 'no_match');
});

test('unsupported action requests have no transaction or messaging side effects', function () {
    Mail::fake();
    Queue::fake();
    $count = Booking::count();
    $response = $this->postJson('/chat', ['message' => 'book this hotel and find activities in Boracay']);
    $response->assertOk()->assertJsonPath('sections.0.outcome.reason', 'action_not_supported')
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('reply'))->toContain('cannot complete a booking')->and(Booking::count())->toBe($count);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('a guest can receive public recommendations alongside a protected task', function () {
    $response = $this->postJson('/chat', ['message' => 'my booking status and hotels in Boracay']);
    $response->assertOk()->assertJsonPath('sections.0.type', IntentRouter::BOOKING_STATUS)
        ->assertJsonPath('sections.0.outcome.reason', 'authentication_required')
        ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
});

test('unknown dining sources are acknowledged without invented restaurants', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay and restaurants nearby']);
    $response->assertOk()->assertJsonPath('sections.1.outcome.reason', 'data_source_not_supported')
        ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
});

test('a stale selected record cannot broaden into a different recommendation', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $this->activity->update(['is_shown' => false]);
    $this->postJson('/chat', ['message' => 'the first activity', 'session_token' => $first->json('session_token')])
        ->assertOk()->assertJsonPath('sections.0.outcome.reason', 'stale_reference');
});

test('fabricated model prices and completed actions are replaced with verified blocks', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[{"task_id":"task-1","text":"I booked your hotel for ₱1."}]}']]]]]]),
    ]);
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $response->assertOk()->assertJsonPath('sections.0.composition', 'deterministic');
    expect($response->json('reply'))->not->toContain('I booked');
});

test('overlapping turns are rejected without creating a user message', function () {
    $session = ChatSession::factory()->create();
    $lock = Cache::lock('chat-turn:'.$session->id, 300);
    $lock->get();
    try {
        $this->postJson('/chat', ['message' => 'find hotels', 'session_token' => $session->session_token])->assertStatus(409);
        expect($session->messages()->count())->toBe(0);
    } finally {
        $lock->release();
    }
});

test('execution limits acknowledge every deferred task', function () {
    config(['services.chatbot.max_tasks' => 1]);
    $this->postJson('/chat', ['message' => 'hotels in Boracay and activities'])->assertOk()
        ->assertJsonCount(2, 'sections')->assertJsonPath('sections.1.outcome.status', 'deferred');
});

test('history keeps section order and scoped cards', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $this->getJson('/chat/history?session_token='.$response->json('session_token'))->assertOk()
        ->assertJsonPath('messages.1.context_data.sections.1.data.retrieved_activities.0.id', $this->activity->id);
});

test('a weather failure preserves the independent hotel result', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]]),
        '*openweathermap*' => Http::response([], 503),
    ]);
    $this->postJson('/chat', ['message' => 'hotels in Boracay and weather'])->assertOk()
        ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)
        ->assertJsonPath('sections.1.outcome.status', 'temporarily_unavailable');
});

test('a missing destination can be supplied once for both pending requests', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels and activities']);
    $first->assertOk()->assertJsonPath('sections.0.outcome.reason', 'missing_destination');
    $this->postJson('/chat', ['message' => 'Boracay', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
});

test('two hotel searches retain their own destinations', function () {
    $destination = DestinationModel::factory()->create(['name' => 'El Nido']);
    $hotel = HotelModel::factory()->create(['hotel_name' => 'Lagoon Stay', 'destination_id' => $destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $this->postJson('/chat', ['message' => 'hotels in Boracay and hotels in El Nido'])->assertOk()
        ->assertJsonPath('sections.0.data.retrieved_hotels.0.id', $this->hotel->id)
        ->assertJsonPath('sections.1.data.retrieved_hotels.0.id', $hotel->id);
});

test('hard budget limits cannot be relaxed by the underlying search fallback', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels under PHP 1000 per night in Boracay and activities']);
    $response->assertOk()->assertJsonPath('sections.0.outcome.reason', 'hard_budget_no_match')
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('retrieved_hotels'))->toBeNull();
});

test('an itinerary preserves the selected hotel and activity', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $response = $this->postJson('/chat', ['message' => 'plan a 2 night itinerary for 2 pax using my selected choices', 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('itinerary.hotel.id', $this->hotel->id)
        ->assertJsonPath('itinerary.activities.0.id', $this->activity->id);
});

test('model outages keep catalog results and offer a real support action', function () {
    config(['services.groq.key' => null]);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response([], 503),
    ]);
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities']);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect(collect($response->json('suggested_actions'))->pluck('id'))->toContain('talk-to-agent');
});

test('a message draft is clearly prepared without claiming to send it', function () {
    Mail::fake();
    $response = $this->postJson('/chat', ['message' => 'draft an email to the hotel about early check-in']);
    $response->assertOk()->assertJsonPath('sections.0.outcome.reason', 'message_draft_prepared');
    expect($response->json('reply'))->toContain('This message has not been sent.');
    Mail::assertNothingSent();
});

test('insisting on an unsupported action preserves the capability boundary', function () {
    $first = $this->postJson('/chat', ['message' => 'book this hotel']);
    $this->postJson('/chat', ['message' => 'just do it', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('sections.0.outcome.reason', 'action_not_supported');
});

test('an excluded activity does not return as a recommendation', function () {
    $this->activity->update(['activity_name' => 'Snorkeling Tour', 'description' => 'Snorkeling in Boracay']);
    $response = $this->postJson('/chat', ['message' => 'activities in Boracay, no snorkeling']);
    $response->assertOk()->assertJsonPath('sections.0.outcome.reason', 'excluded_features');
    expect($response->json('retrieved_activities'))->toBeNull();
});

test('a missing budget basis can be clarified on the next turn', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay under PHP 3000 and activities']);
    $first->assertOk()->assertJsonPath('sections.0.outcome.reason', 'missing_budget_basis');
    $this->postJson('/chat', ['message' => 'per night', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
});
