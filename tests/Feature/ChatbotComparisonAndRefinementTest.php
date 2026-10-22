<?php

use App\Models\ActivityModel;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\Chat\ConversationPlanner;
use App\Services\Chat\IntentRouter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.chatbot.planner_enabled' => false, 'services.chatbot.conversational_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        // A model reply that would replace the comparison table if allowed.
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[{"task_id":"task-1","text":"Both are great options."}]}']]]]]]),
    ]);
    $this->marikit = DestinationModel::factory()->create(['name' => 'Marikit']);
    $this->lagawan = DestinationModel::factory()->create(['name' => 'Lagawan']);
    $this->resort = HotelModel::factory()->create(['hotel_name' => 'Palmera Resort', 'destination_id' => $this->marikit->id, 'featured_amenities' => ['Swimming Pool'], 'embedding' => unitVectorString(0)]);
    $this->inn = HotelModel::factory()->create(['hotel_name' => 'Lawa Inn', 'destination_id' => $this->lagawan->id, 'featured_amenities' => ['WiFi'], 'embedding' => unitVectorString(0)]);
    $this->standard = RoomType::factory()->create(['hotel_id' => $this->resort->id, 'room_name' => 'Palmera Standard', 'base_price' => 2900, 'base_occupancy' => 2, 'max_occupancy' => 2, 'additional_notes' => null, 'embedding' => unitVectorString(0)]);
    $this->suite = RoomType::factory()->create(['hotel_id' => $this->resort->id, 'room_name' => 'Palmera Family Suite', 'base_price' => 5200, 'base_occupancy' => 2, 'max_occupancy' => 4, 'embedding' => unitVectorString(0)]);
});

test('named rooms are compared side by side from catalog fields, never from model prose', function () {
    $response = $this->postJson('/chat', ['message' => 'compare Palmera Standard and Palmera Family Suite for 4 pax']);

    $response->assertOk()->assertJsonPath('sections.0.type', 'COMPARISON')->assertJsonPath('sections.0.composition', 'deterministic');
    $answer = $response->json('sections.0.answer');
    expect($answer)->toContain('| Price basis |')->toContain('₱2,900 per night')->toContain('Not documented')
        ->toContain('fits 4 guests')->toContain('not verified in the catalog: room for 4 guests')
        ->not->toContain('Both are great options')->not->toMatch('/\d+\s?%/')
        ->and(array_column($response->json('retrieved_rooms'), 'id'))->toBe([$this->standard->id, $this->suite->id]);
});

test('hotels in different destinations can be compared by name', function () {
    $response = $this->postJson('/chat', ['message' => 'Palmera Resort vs Lawa Inn']);

    expect(array_column($response->json('retrieved_hotels'), 'id'))->toBe([$this->resort->id, $this->inn->id]);
});

test('a comparison preserves a separate activity request', function (string $separator) {
    $activity = ActivityModel::factory()->create(['activity_name' => 'Reef Adventure', 'destination_id' => $this->marikit->id, 'embedding' => unitVectorString(0)]);

    $response = $this->postJson('/chat', ['message' => 'compare Palmera Resort and Lawa Inn'.$separator.'suggest activities in Marikit']);

    $response->assertOk();
    expect(array_column($response->json('sections'), 'type'))->toContain('COMPARISON', 'ACTIVITY_SEARCH')
        ->and(array_column($response->json('retrieved_activities'), 'id'))->toContain($activity->id)
        ->and(array_column($response->json('retrieved_hotels'), 'id'))->toEqualCanonicalizing([$this->resort->id, $this->inn->id]);
})->with(['; also ', ' and ', ' plus ']);

test('comparison names containing connectors stay intact alongside another intent', function () {
    $this->resort->update(['hotel_name' => 'Hotel Sun and Sea']);
    $this->inn->update(['hotel_name' => 'Hotel Bay']);
    ActivityModel::factory()->create(['destination_id' => $this->marikit->id, 'embedding' => unitVectorString(0)]);
    config(['services.chatbot.guided_conversation_enabled' => true]);

    $response = $this->postJson('/chat', ['message' => 'compare Hotel Sun and Sea and Hotel Bay and activities in Marikit']);

    $response->assertOk();
    expect(array_column($response->json('sections'), 'type'))->toContain('COMPARISON', 'ACTIVITY_SEARCH')
        ->and(array_column($response->json('retrieved_hotels'), 'id'))->toEqualCanonicalizing([$this->resort->id, $this->inn->id]);
});

test('a comparison retains guidance even when that guidance has no reviewed source', function () {
    $response = $this->postJson('/chat', ['message' => 'compare Palmera Resort and Lawa Inn and where can we eat in Marikit']);

    $response->assertOk();
    expect(array_column($response->json('sections'), 'type'))->toContain('COMPARISON', 'KNOWLEDGE_QUERY')
        ->and($response->json('retrieved_hotels'))->toHaveCount(2)
        ->and(collect($response->json('sections'))->firstWhere('type', 'KNOWLEDGE_QUERY')['outcome']['status'])->toBe('no_match');
});

test('hotel comparison budgets cannot use a cheaper room that is too small for the party', function () {
    $response = $this->postJson('/chat', ['message' => 'compare Palmera Resort and Lawa Inn for 3 pax, budget 3500 per night']);

    $response->assertOk()->assertJsonPath('sections.0.type', 'COMPARISON');
    expect($response->json('sections.0.answer'))->not->toContain('within your budget');
});

test('a shared trip budget is not independently promised for both a comparison and activities', function () {
    ActivityModel::factory()->create(['destination_id' => $this->marikit->id, 'embedding' => unitVectorString(0)]);

    $response = $this->postJson('/chat', ['message' => 'compare Palmera Standard and Palmera Family Suite and suggest activities in Marikit; for 2 pax for 2 nights; total budget 20000']);

    $response->assertOk();
    $comparison = collect($response->json('sections'))->firstWhere('type', 'COMPARISON');
    expect($comparison['constraints']['max_price'])->toBeNull()
        ->and($comparison['answer'])->not->toContain('within your budget')
        ->and(array_column($response->json('sections'), 'type'))->toContain('ACTIVITY_SEARCH');
});

test('comparison affordability includes stay length and extra guests', function (string $constraints, bool $fits) {
    $this->suite->update(['base_price' => 3000, 'base_occupancy' => 2, 'max_occupancy' => 4, 'extra_person_fee' => 1000]);

    $response = $this->postJson('/chat', ['message' => 'compare Palmera Standard and Palmera Family Suite '.$constraints]);

    $response->assertOk()->assertJsonPath('sections.0.type', 'COMPARISON');
    $answer = $response->json('sections.0.answer');
    if ($fits) {
        expect($answer)->toContain('within your budget');
    } else {
        expect($answer)->not->toContain('within your budget');
    }
})->with([
    'whole stay exceeds budget' => ['for 2 pax for 2 nights, total budget 5000', false],
    'nightly budget fits' => ['for 2 pax, budget 3500 per night', true],
    'extra guest charge exceeds budget' => ['for 3 pax, budget 3500 per night', false],
    'unknown stay length' => ['for 2 pax, total budget 5000', false],
    'unknown budget basis' => ['for 2 pax, budget 5000', false],
    'unknown headcount' => ['budget 5000 per night', false],
]);

test('a comparison of a listing hidden since it was shown is refused rather than guessed', function () {
    $first = $this->postJson('/chat', ['message' => 'rooms in Marikit']);
    $this->suite->update(['is_shown' => false]);

    $this->postJson('/chat', ['message' => 'compare the first and second room', 'session_token' => $first->json('session_token')])
        ->assertOk()->assertJsonPath('sections.0.outcome.reason', 'stale_reference');
});

test('"cheaper ones" keeps only options below the highest price shown, cheapest first', function () {
    $paddle = ActivityModel::factory()->create(['activity_name' => 'Shoreline Paddle Tour', 'destination_id' => $this->marikit->id, 'rate' => '₱500/person', 'embedding' => unitVectorString(0)]);
    $sail = ActivityModel::factory()->create(['activity_name' => 'Paraw Sunset Sail', 'destination_id' => $this->marikit->id, 'rate' => '₱1,200/person', 'embedding' => unitVectorString(0)]);
    ActivityModel::factory()->create(['activity_name' => 'Skyward Parasail', 'destination_id' => $this->marikit->id, 'rate' => '₱1,800/person', 'embedding' => unitVectorString(0)]);
    $first = $this->postJson('/chat', ['message' => 'activities in Marikit']);

    $response = $this->postJson('/chat', ['message' => 'cheaper ones', 'session_token' => $first->json('session_token')]);

    expect(array_column($response->json('retrieved_activities'), 'id'))->toBe([$paddle->id, $sail->id])
        ->and($response->json('result_ordering'))->toBe('price_asc');
});

test('a party too large for every room gets no rooms instead of rooms that cannot hold it', function () {
    $response = $this->postJson('/chat', ['message' => 'rooms for 10 pax in Marikit']);

    expect($response->json('retrieved_rooms'))->toBeEmpty();
});

test('children count toward capacity', function () {
    $response = $this->postJson('/chat', ['message' => 'rooms in Marikit for 2 adults and 1 child']);

    expect(array_column($response->json('retrieved_rooms') ?? [], 'id'))->not->toContain($this->standard->id)
        ->and($response->json('trace.constraints.pax') ?? $response->json('sections.0.constraints.pax'))->toBe(3);
});

test('a hotel whose name only loosely matches is ignored outside the named destination', function () {
    $constraints = app(IntentRouter::class)->extractConstraints('kwarto para sa lawa tao sa Marikit');

    expect($constraints['destination_id'])->toBe($this->marikit->id)->and($constraints['hotel_id'])->toBeNull();
});

test('a trailing "max" after the basis is a hard ceiling', function () {
    $response = $this->postJson('/chat', ['message' => 'rooms for 2 in Marikit, 3000 per night max']);

    expect(array_column($response->json('retrieved_rooms') ?? [], 'id'))->toBe([$this->standard->id]);
});

test('accepted trip facts record corrections and survive a destination change', function () {
    $first = $this->postJson('/chat', ['message' => 'rooms in Marikit for 2 from 2026-12-10 to 2026-12-12']);
    $token = $first->json('session_token');
    $this->postJson('/chat', ['message' => 'make it 3 pax, same dates', 'session_token' => $token])->assertOk();
    $this->postJson('/chat', ['message' => 'what about Lagawan instead? same dates', 'session_token' => $token])->assertOk();

    $facts = ChatSession::where('session_token', $token)->sole()->metadata['conversation_v2']['trip_facts'];
    expect($facts['destination_id'])->toBe($this->lagawan->id)
        ->and($facts['pax'])->toBe(3)
        ->and($facts['check_in_date'])->toBe('2026-12-10')
        ->and(collect($facts['corrections'])->firstWhere('field', 'pax'))->toMatchArray(['from' => 2, 'to' => 3])
        ->and($facts)->not->toHaveKey('reply');
});

test('Filipino and higher ordinals map to result positions', function (string $word, int $position) {
    expect(ConversationPlanner::ordinalPosition($word))->toBe($position);
})->with([['una', 0], ['pangalawa', 1], ['ikatlo', 2], ['fourth', 3], ['ikalima', 4]]);
