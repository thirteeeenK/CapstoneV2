<?php

use App\Models\ActivityModel;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use App\Services\Chat\IntentRouter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.chatbot.guided_conversation_enabled' => true, 'services.chatbot.planner_enabled' => false]);
    $this->travelTo(now()->setDate(2026, 10, 21));
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]]),
    ]);
    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create(['hotel_name' => 'Palm Beach Resort', 'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'room_name' => 'Palm Deluxe Suite', 'base_price' => 2500, 'base_occupancy' => 2, 'max_occupancy' => 4, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $this->activity = ActivityModel::factory()->create(['activity_name' => 'Parasailing Adventure', 'destination_id' => $this->destination->id, 'is_shown' => true, 'rate' => '900', 'embedding' => unitVectorString(0)]);
});

test('gathers only missing itinerary details and accepts several answers together', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $first->assertOk()->assertJsonPath('clarification.field', 'destination');
    $second = $this->postJson('/chat', ['message' => 'Boracay, dalawa kami', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('clarification.field', 'days')->assertJsonPath('sections.0.constraints.pax', 2);
    $third = $this->postJson('/chat', ['message' => 'three days', 'session_token' => $first->json('session_token')]);
    $third->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('sections.0.outcome.reason', 'undated_itinerary_draft')
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($third->json('reply'))->toContain('undated')->not->toContain('Cost breakdown');
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    expect($session->metadata['conversation_v2']['dialogue']['tasks'][0]['persistent_id'])->toBe($first->json('sections.0.persistent_id'));
});

test('a complete activity discovery request does not start a questionnaire', function () {
    $this->postJson('/chat', ['message' => 'extreme activities in Boracay'])->assertOk()
        ->assertJsonPath('clarification', null)->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
});

test('one destination answer unlocks every pending category', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels and activities']);
    $first->assertOk()->assertJsonPath('clarification.field', 'destination');
    expect(substr_count($first->json('reply'), '?'))->toBe(1);
    $this->postJson('/chat', ['message' => 'Boracay', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id)
        ->assertJsonPath('clarification', null);
});

test('reported ambiguous target returns both card sets and one budget question', function (string $message) {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $response = $this->postJson('/chat', ['message' => $message, 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id)
        ->assertJsonPath('clarification.field', 'budget_scope');
    expect(substr_count($response->json('reply'), '?'))->toBe(1);
    expect($response->json('reply'))->toContain('catalog previews', 'not a checked trip total');
    $followup = $this->postJson('/chat', ['message' => 'per night', 'session_token' => $first->json('session_token')]);
    $followup->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
})->with(['activities and hotel for 2 pax 10000 budget', 'hotel for 2 pax 10000 budget and activities']);

test('a standalone shared budget is preserved for clarification', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels and activities in Boracay, 10000 budget']);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('clarification.field'))->toBeIn(['budget_scope', 'budget_basis']);
    expect($response->json('sections.0.constraints.max_price'))->toBe(10000);
});

test('a joint-budget turn asks one question while retaining both preview sections', function (bool $guided) {
    config(['services.chatbot.guided_conversation_enabled' => $guided]);
    $response = $this->postJson('/chat', ['message' => 'hotels and activities in Boracay, total budget 20000']);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect(substr_count($response->json('reply'), '?'))->toBe(1);
    foreach ($response->json('sections') as $section) {
        if (! empty($section['card_refs'])) {
            expect($section['answer'])->not->toContain('?');
            expect($section['data']['budget_unverified'] ?? false)->toBeTrue();
        }
    }
    $session = ChatSession::where('session_token', $response->json('session_token'))->firstOrFail();
    $history = $session->messages()->where('sender', 'bot')->latest('id')->first();
    expect(substr_count($history->message, '?'))->toBe(1);
})->with(['guided' => true, 'legacy' => false]);

test('a typed nightly refinement keeps the per-person basis while dates are pending', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a 2 night trip in Boracay for 2 people under PHP 1000 per person for the whole stay']);
    $first->assertOk()->assertJsonPath('clarification.field', 'dates');
    $second = $this->postJson('/chat', ['message' => 'per night', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('clarification.field', 'dates');
    $tasks = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail()->metadata['conversation_v2']['dialogue']['tasks'];
    expect($tasks[0]['budget_basis'])->toBe('per_person')
        ->and($tasks[0]['budget_period'])->toBe('per_night')
        ->and($tasks[0]['constraints']['max_price'])->toBe(1000);
});

test('an explicit group-budget correction can replace the per-person basis', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax under PHP 3000 per person']);
    $first->assertOk()->assertJsonPath('clarification.field', 'budget_period');
    $second = $this->postJson('/chat', ['message' => 'per night for the group', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('clarification', null);
    $tasks = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail()->metadata['conversation_v2']['dialogue']['tasks'];
    expect($tasks[0]['budget_basis'])->toBe('per_night')
        ->and($tasks[0]['constraints']['max_price'])->toBe(3000);
});

test('a bare nightly refinement preserves a per-person budget after a no-match turn', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax under PHP 1000 per person']);
    $first->assertOk()->assertJsonPath('clarification', null);
    $second = $this->postJson('/chat', ['message' => 'per night', 'session_token' => $first->json('session_token')]);
    $second->assertOk();
    $tasks = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail()->metadata['conversation_v2']['dialogue']['tasks'];
    expect($tasks[0]['budget_basis'])->toBe('per_person')
        ->and($tasks[0]['budget_period'] ?? null)->toBe('per_night');
});

test('dates resume availability while an added task is retained', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay and activities']);
    $first->assertOk()->assertJsonPath('clarification.field', 'dates')->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    $second = $this->postJson('/chat', ['message' => '2026-11-12 to 2026-11-15, also airport transfers', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('sections.0.constraints.check_in_date', '2026-11-12')->assertJsonPath('sections.0.constraints.check_out_date', '2026-11-15');
    expect($second->json('multi_intents'))->toContain(IntentRouter::ADDON_SEARCH);
});

test('undecided availability dates fall back to discovery without repeating the question', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $first->assertOk()->assertJsonPath('clarification.field', 'dates');
    $second = $this->postJson('/chat', ['message' => 'di pa sure', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('retrieved_rooms.0.id', $this->room->id);
    expect($second->json('reply'))->toContain('Dated availability has not been checked');
});

test('short pax answers and corrections update group cost scope', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a 2 night trip in Boracay with PHP 10000 total budget']);
    $first->assertOk()->assertJsonPath('clarification.field', 'pax');
    $second = $this->postJson('/chat', ['message' => 'dalawa', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('sections.0.constraints.pax', 2)->assertJsonPath('sections.0.constraints.max_price', 10000);
    $third = $this->postJson('/chat', ['message' => 'actually apat kami, same budget', 'session_token' => $first->json('session_token')]);
    $third->assertOk()->assertJsonPath('sections.0.constraints.pax', 4)->assertJsonPath('sections.0.constraints.max_price', 10000);
});

test('invalid replies stop repeating after bounded recovery', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $second = $this->postJson('/chat', ['message' => 'hmm', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('clarification.field', 'destination');
    $third = $this->postJson('/chat', ['message' => 'hmm again', 'session_token' => $first->json('session_token')]);
    $third->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('sections.0.outcome.status', 'deferred');
});

test('a new FAQ suspends planning and an explicit continuation resumes it', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $second = $this->postJson('/chat', ['message' => 'what destinations do you support?', 'session_token' => $first->json('session_token')]);
    $second->assertOk();
    expect($second->json('clarification'))->toBeNull();
    $this->postJson('/chat', ['message' => 'continue planning', 'session_token' => $first->json('session_token')])->assertOk()->assertJsonPath('clarification.field', 'destination');
});

test('cancelling planning does not request a booking cancellation', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', ['message' => 'never mind', 'session_token' => $first->json('session_token')])->assertOk()->assertJsonPath('clarification', null);
    $this->postJson('/chat', ['message' => 'cancel my booking', 'session_token' => $first->json('session_token')])->assertOk()->assertJsonPath('sections.0.outcome.reason', 'action_not_supported');
});

test('a budget cannot turn invalid stay dates into a quote', function (string $dates) {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $response = $this->postJson('/chat', ['message' => $dates, 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('clarification.field', 'dates');
    expect($response->json('retrieved_rooms'))->toBeNull();
})->with(['2026-11-15 to 2026-11-12', '2026-09-12 to 2026-09-15', '11/12/2026 to 11/15/2026']);

test('a combined total is retained once and gathers duration before costing', function (string $message) {
    $response = $this->postJson('/chat', ['message' => $message]);
    $response->assertOk()->assertJsonPath('clarification.field', 'days');
    $tasks = collect($response->json('sections'));
    expect($tasks->firstWhere('type', IntentRouter::ITINERARY_QUERY)['constraints']['max_price'])->toBe(10000);
    expect($response->json('itinerary'))->toBeNull();
    $this->postJson('/chat', ['message' => 'three days', 'session_token' => $response->json('session_token')])->assertOk()
        ->assertJsonPath('clarification.field', 'nights');
})->with(['hotels and activities in Boracay for 2 pax, PHP 10000 total for both']);

test('clarifying a reported target as a trip total retains the amount and both needs', function () {
    $first = $this->postJson('/chat', ['message' => 'activities and hotel in Boracay for 2 pax 10000 budget']);
    $second = $this->postJson('/chat', ['message' => 'whole trip for both of us', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('clarification.field', 'days');
    expect(collect($second->json('sections'))->firstWhere('type', IntentRouter::ITINERARY_QUERY)['constraints']['max_price'])->toBe(10000);
});

test('stale structured answers cannot mutate the latest question or history', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', ['message' => 'Boracay', 'session_token' => $first->json('session_token')])->assertOk();
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    $count = $session->messages()->count();
    $this->postJson('/chat', ['message' => 'skip', 'session_token' => $first->json('session_token'), 'question_id' => $first->json('clarification.id'), 'question_revision' => $first->json('clarification.revision')])
        ->assertStatus(409)->assertJsonPath('status', 'stale_question')->assertJsonPath('clarification.field', 'days');
    expect($session->messages()->count())->toBe($count);
});

test('a valid structured answer can use the undecided option', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $this->postJson('/chat', ['message' => 'Dates undecided', 'session_token' => $first->json('session_token'), 'question_id' => $first->json('clarification.id'), 'question_revision' => $first->json('clarification.revision')])
        ->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('retrieved_rooms.0.id', $this->room->id);
});

test('history restores the same single shared question and budget previews', function () {
    $first = $this->postJson('/chat', ['message' => 'activities and hotel in Boracay for 2 pax 10000 budget']);
    $this->getJson('/chat/history?session_token='.$first->json('session_token'))->assertOk()
        ->assertJsonPath('messages.1.context_data.clarification.id', $first->json('clarification.id'))
        ->assertJsonPath('messages.1.context_data.retrieved_hotels.0.id', $this->hotel->id);
});

test('two adults and two children are four travelers', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a 2 night itinerary in Boracay with PHP 10000 total budget']);
    $this->postJson('/chat', ['message' => 'two adults and two children', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('sections.0.constraints.pax', 4);
});

test('a new destination invalidates selected entities from the previous destination', function () {
    $other = DestinationModel::factory()->create(['name' => 'El Nido']);
    $hotel = HotelModel::factory()->create(['hotel_name' => 'Lagoon Resort', 'destination_id' => $other->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_price' => 2000, 'is_shown' => true]);
    $first = $this->postJson('/chat', ['message' => 'hotels and activities in Boracay']);
    $response = $this->postJson('/chat', ['message' => 'actually El Nido', 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $hotel->id);
    expect($response->json('retrieved_activities'))->toBeNull();
});

test('a new category with multiple prior destinations asks which scope to use', function () {
    DestinationModel::factory()->create(['name' => 'El Nido']);
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay and hotels in El Nido']);
    $response = $this->postJson('/chat', ['message' => 'activities and hotels', 'session_token' => $first->json('session_token')]);
    $response->assertOk();
    expect($response->json('retrieved_activities'))->toBeNull();
    expect($response->json('retrieved_hotels'))->toBeNull();
});

test('explicit hard ceilings are not relaxed while their basis is unknown', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay under PHP 100 and activities']);
    $response->assertOk()->assertJsonPath('clarification.field', 'budget_basis')->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('retrieved_hotels'))->toBeNull();
    $followup = $this->postJson('/chat', ['message' => 'per night', 'session_token' => $response->json('session_token')]);
    $followup->assertOk()->assertJsonPath('sections.0.outcome.reason', 'hard_budget_no_match');
    expect($followup->json('retrieved_hotels'))->toBeNull();
});

test('a low target is a labelled preview and hidden inventory remains hidden', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay with PHP 100 budget and activities']);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('sections.0.data.budget_unverified', true);
    $this->hotel->update(['is_shown' => false]);
    $this->postJson('/chat', ['message' => 'hotels in Boracay with PHP 100 budget and activities'])->assertOk()
        ->assertJsonPath('sections.0.outcome.status', 'no_match')->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
});

test('model outages still permit deterministic questions', function () {
    config(['services.groq.key' => null]);
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $first->assertOk()->assertJsonPath('clarification.field', 'destination');
    Http::assertNothingSent();
});

test('date ranges retain an explicit future year', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $this->postJson('/chat', ['message' => 'November 12–15, 2027', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('sections.0.constraints.check_in_date', '2027-11-12')->assertJsonPath('sections.0.constraints.check_out_date', '2027-11-15');
});

test('unscoped numbers do not become travelers automatically', function () {
    $response = $this->postJson('/chat', ['message' => '2']);
    $response->assertOk();
    expect($response->json('reply'))->toContain('whether that number means travelers');
});

test('reported budget previews work while the guided feature flag is disabled', function () {
    config(['services.chatbot.guided_conversation_enabled' => false]);
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $response = $this->postJson('/chat', ['message' => 'activities and hotel for 2 pax 10000 budget', 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id)
        ->assertJsonPath('sections.1.outcome.reason', 'missing_budget_basis')->assertJsonPath('sections.1.data.budget_unverified', true);
    $this->postJson('/chat', ['message' => 'per night', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
});

test('preview discovery still honors group capacity', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay for 8 pax 10000 budget and activities']);
    $response->assertOk()->assertJsonPath('sections.0.outcome.status', 'no_match');
    expect($response->json('retrieved_hotels'))->toBeNull();
});

test('visible card limits also limit the deterministic result count', function () {
    config(['services.chatbot.cards_per_task' => 1]);
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id, 'hotel_name' => 'Another Resort', 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_price' => 3000, 'is_shown' => true]);
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax 10000 budget and activities']);
    $response->assertOk()->assertJsonCount(1, 'sections.0.data.retrieved_hotels');
    expect($response->json('sections.0.answer'))->toContain('I found 1 verified option.')->not->toContain('I found 2 verified options.');
});

test('guided questions are suspended while a human support agent controls the session', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    SupportInquiry::create(['chat_session_id' => $session->id, 'ticket_number' => 'GUIDED-TEST', 'status' => SupportInquiry::STATUS_HUMAN_ACTIVE]);
    $this->postJson('/chat', ['message' => 'Boracay', 'session_token' => $first->json('session_token')])->assertOk()->assertJsonPath('control', 'admin');
    expect($session->fresh()->metadata['conversation_v2']['dialogue']['active_question'])->toBeNull();
    Http::assertNothingSent();
});

test('unsupported actions do not start a booking questionnaire', function () {
    $this->postJson('/chat', ['message' => 'book this hotel'])->assertOk()->assertJsonPath('sections.0.outcome.reason', 'action_not_supported')->assertJsonPath('clarification', null);
});

test('an explicitly unsupported destination cannot silently reuse a previous trip', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels and activities in Boracay']);
    $response = $this->postJson('/chat', ['message' => 'hotels and activities in Tokyo', 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('sections.0.outcome.reason', 'unsupported_destination');
    expect($response->json('retrieved_hotels'))->toBeNull();
    expect($response->json('retrieved_activities'))->toBeNull();
});

test('a legacy pending clarification can enter the guided dialogue', function () {
    config(['services.chatbot.guided_conversation_enabled' => false]);
    $first = $this->postJson('/chat', ['message' => 'hotels and activities']);
    config(['services.chatbot.guided_conversation_enabled' => true]);
    $response = $this->postJson('/chat', ['message' => 'Boracay', 'session_token' => $first->json('session_token')]);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id)->assertJsonPath('clarification', null);
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    expect($session->metadata['conversation_v2']['pending'])->toBeEmpty();
});

test('repeated invalid dates pause availability instead of looping', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $this->postJson('/chat', ['message' => '2026-11-15 to 2026-11-12', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('clarification.field', 'dates');
    $this->postJson('/chat', ['message' => '2026-11-16 to 2026-11-12', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('clarification', null)->assertJsonPath('sections.0.outcome.status', 'deferred');
});

test('zero travelers is invalid and never becomes an implicit two-person quote', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a 2 night itinerary in Boracay with PHP 10000 total budget']);
    $this->postJson('/chat', ['message' => '0', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('clarification.field', 'pax')->assertJsonPath('sections.0.constraints.pax', null);
});

test('per-person clarification applies an unresolved shared budget to activities and narrows the stay', function () {
    $first = $this->postJson('/chat', ['message' => 'activities and hotel in Boracay for 2 pax 10000 budget']);
    $response = $this->postJson('/chat', ['message' => 'per person', 'session_token' => $first->json('session_token')]);
    $hotel = collect($response->json('sections'))->firstWhere('type', IntentRouter::HOTEL_SEARCH);
    $activity = collect($response->json('sections'))->firstWhere('type', IntentRouter::ACTIVITY_SEARCH);

    // Activities can price a per-person budget with the known headcount.
    expect($activity['constraints']['max_price'])->toBe(10000);
    expect($activity['data']['budget_unverified'] ?? false)->toBeFalse();

    // A stay cannot: "per person" does not say per person per night or per
    // person for the whole stay. The amount is kept for display but not
    // applied, the cards are labelled previews, and the narrower question is
    // asked once instead of re-asking the basis.
    expect($hotel['constraints']['max_price'])->toBe(10000);
    expect($hotel['data']['budget_unverified'])->toBeTrue();
    expect($hotel['answer'])->toContain('budget has not been applied');
    $response->assertOk()
        ->assertJsonPath('clarification.field', 'budget_period')
        ->assertJsonPath('sections.1.outcome.reason', 'missing_budget_period');
    expect($response->json('clarification.text'))->toContain('per night');
    expect($response->json('reply'))->not->toContain('budget basis');
    expect(substr_count($response->json('reply'), '?'))->toBe(1);
});

test('answering the narrower budget question resolves it without asking again', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax, 10000 budget per person']);
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', ['message' => 'per night', 'session_token' => $token]);
    $second->assertOk()->assertJsonPath('clarification', null);
    expect(collect($second->json('sections'))->firstWhere('type', IntentRouter::HOTEL_SEARCH)['constraints']['max_price'])->toBe(10000);
    $state = ChatSession::where('session_token', $token)->firstOrFail()->metadata['conversation_v2']['dialogue'];
    expect($state['tasks'][0]['budget_basis'])->toBe('per_person')
        ->and($state['tasks'][0]['budget_period'])->toBe('per_night');
});

test('a clicked traveler count advances the question and returns the readable answer', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a 2 night trip in Boracay with PHP 10000 total budget']);
    $first->assertOk()->assertJsonPath('clarification.field', 'pax');

    $second = $this->postJson('/chat', [
        'session_token' => $first->json('session_token'),
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => ['pax' => '2'],
    ]);

    $second->assertOk()->assertJsonPath('sections.0.constraints.pax', 2)
        ->assertJsonPath('clarification.field', 'dates')->assertJsonPath('user_message', '2 travelers');
    expect(ChatSession::where('session_token', $first->json('session_token'))->firstOrFail()
        ->messages()->where('sender', 'user')->latest('id')->value('message'))->toBe('2 travelers');
});

test('itinerary quotes normalize the original amount without replacing it in dialogue', function (string $budget, array $answers, int $normalized, string $scope) {
    $response = $this->postJson('/chat', ['message' => 'plan a 2 night trip in Boracay for 2 people '.$budget]);
    $token = $response->json('session_token');
    foreach ($answers as $answer) {
        $response = $this->postJson('/chat', ['message' => $answer, 'session_token' => $token]);
        $response->assertOk();
    }
    $response->assertJsonPath('clarification', null)
        ->assertJsonPath('itinerary.budget', $normalized)
        ->assertJsonPath('itinerary.budget_check.scope', $scope);
    expect($response->json('sections.0.constraints.max_price'))->toBe(3000);
    if ($scope === 'lodging') {
        expect($response->json('itinerary.budget_check.compared_total'))->toBe($response->json('itinerary.room.total'));
        expect($response->json('reply'))->toContain('hotel only', 'Activities cost');
    }
})->with([
    'per person for the trip' => ['under PHP 3000 per person', ['for the whole stay', '2026-11-12 to 2026-11-14'], 6000, 'trip'],
    'per person per night' => ['under PHP 3000 per person per night', ['2026-11-12 to 2026-11-14'], 12000, 'trip'],
    'nightly total' => ['under PHP 3000 per night', ['everything including activities', '2026-11-12 to 2026-11-14'], 6000, 'trip'],
    'nightly hotel only' => ['under PHP 3000 per night', ['the hotel only', '2026-11-12 to 2026-11-14'], 6000, 'lodging'],
]);

test('malformed structured fields return validation errors without changing dialogue', function (mixed $value) {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    $before = $session->metadata;
    $count = $session->messages()->count();

    $this->postJson('/chat', [
        'session_token' => $session->session_token,
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => ['destination' => $value],
    ])->assertStatus(422);
    expect($session->fresh()->metadata)->toBe($before)
        ->and($session->messages()->count())->toBe($count);
})->with(['array' => [[]], 'object' => [['id' => '1']], 'boolean' => [true]]);

test('a structured answer requires its current question and cannot replace a typed message silently', function () {
    $this->postJson('/chat', ['answer' => ['pax' => '2']])->assertStatus(422)->assertJsonValidationErrors('question_id');
    $this->postJson('/chat', ['message' => 'hotels in Boracay', 'answer' => ['pax' => '2']])
        ->assertStatus(422)->assertJsonValidationErrors('message');
});

test('a per-person nightly hotel ceiling uses group prices during retrieval and final filtering', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax under PHP 1500 per person']);
    $first->assertOk()->assertJsonPath('clarification.field', 'budget_period');
    $second = $this->postJson('/chat', [
        'session_token' => $first->json('session_token'),
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => ['budget_period' => 'per_night'],
    ]);
    $second->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
    expect(collect($second->json('trip_summary.items'))->firstWhere('field', 'budget')['value'])->toContain('per person per night');
});

test('a known location and an unsupported location retain separate outcomes', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay and activities in Tokyo']);
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('sections.1.outcome.reason', 'unsupported_destination');
    expect($response->json('retrieved_activities'))->toBeNull();
});

test('malformed dialogue state falls back safely to a new request', function () {
    $session = ChatSession::factory()->create(['metadata' => ['conversation_v2' => ['dialogue' => ['version' => 999, 'tasks' => 'broken', 'active_question' => ['field' => 'pax']]]]]);
    $this->postJson('/chat', ['message' => 'activities in Boracay', 'session_token' => $session->session_token])->assertOk()
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id)->assertJsonPath('clarification', null);
});

test('dates and travelers supplied together complete a pending availability check', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $this->postJson('/chat', ['message' => '2026-11-12 to 2026-11-15 for 2 pax', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('clarification', null)->assertJsonPath('retrieved_rooms.0.id', $this->room->id);
});

test('a priced itinerary waits for stay dates instead of inventing them', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a 2 night itinerary in Boracay for 2 pax with PHP 10000 total budget']);
    $first->assertOk()->assertJsonPath('clarification.field', 'dates');
    expect($first->json('itinerary'))->toBeNull();
    $second = $this->postJson('/chat', ['message' => '2026-11-12 to 2026-11-14', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('sections.0.constraints.nights', 2);
    expect($second->json('itinerary'))->not->toBeNull();
});

test('per-person activity budgets are compared with group quotes consistently', function () {
    $response = $this->postJson('/chat', ['message' => 'activities in Boracay for 2 pax with PHP 1000 budget per person']);
    $response->assertOk()->assertJsonPath('retrieved_activities.0.id', $this->activity->id)
        ->assertJsonPath('retrieved_activities.0.over_budget', false)->assertJsonPath('retrieved_activities.0.price_quote.total', 1800);
});

test('a nightly hotel ceiling includes the extra traveler charges', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay for 4 pax under PHP 3000 per night and activities']);
    $response->assertOk()->assertJsonPath('sections.0.outcome.reason', 'hard_budget_no_match')->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('retrieved_hotels'))->toBeNull();
});

test('a volunteered detail is kept and acknowledged without an invalid strike', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a trip to Boracay']);
    $first->assertOk()->assertJsonPath('clarification.field', 'days');

    // Answering a duration question with a headcount is progress, not a
    // failed answer: the value is kept, confirmed, and the open question is
    // asked once more without "I could not confirm that detail".
    $second = $this->postJson('/chat', ['message' => '2 people', 'session_token' => $first->json('session_token')]);
    $second->assertOk()
        ->assertJsonPath('clarification.field', 'days')
        ->assertJsonPath('clarification.attempts', 0)
        ->assertJsonPath('sections.0.constraints.pax', 2);
    expect($second->json('clarification.text'))->toContain('Got it — 2 travelers')
        ->and($second->json('reply'))->not->toContain('could not confirm');

    // No internal slot name reaches the transcript.
    expect($second->json('reply'))->not->toContain('pax')->not->toContain('budget_basis');

    $third = $this->postJson('/chat', ['message' => '3 days', 'session_token' => $first->json('session_token')]);
    $third->assertOk()->assertJsonPath('clarification', null)
        ->assertJsonPath('sections.0.constraints.pax', 2)
        ->assertJsonPath('sections.0.constraints.days', 3);
});

test('an invalid answer still recovers and resumes', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a trip to Boracay']);
    $token = $first->json('session_token');

    $this->postJson('/chat', ['message' => 'zero days', 'session_token' => $token])->assertOk()
        ->assertJsonPath('clarification.field', 'days')->assertJsonPath('clarification.attempts', 1);
    $paused = $this->postJson('/chat', ['message' => 'zero days', 'session_token' => $token]);
    $paused->assertOk()->assertJsonPath('clarification', null);
    expect($paused->json('reply'))->toContain('paused');

    $this->postJson('/chat', ['message' => 'continue planning', 'session_token' => $token])->assertOk();
    $this->postJson('/chat', ['message' => '3 days', 'session_token' => $token])->assertOk()
        ->assertJsonPath('sections.0.constraints.days', 3);
});

test('a trailing budget clause is a constraint, not another task', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax, total budget 20000']);

    $response->assertOk()->assertJsonPath('clarification', null);
    expect($response->json('sections'))->toHaveCount(1);
    expect($response->json('sections.0.type'))->toBe(IntentRouter::HOTEL_SEARCH);
    expect($response->json('sections.0.constraints.max_price'))->toBe(20000);
});

test('equivalent repeated requests reuse one saved task', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $token = $first->json('session_token');
    $this->postJson('/chat', ['message' => 'hotels in Boracay', 'session_token' => $token])->assertOk();
    $this->postJson('/chat', ['message' => 'show me hotels in Boracay again', 'session_token' => $token])->assertOk();

    $tasks = ChatSession::where('session_token', $token)->firstOrFail()
        ->metadata['conversation_v2']['dialogue']['tasks'];

    expect($tasks)->toHaveCount(1);
    expect($tasks[0]['persistent_id'])->toBe($first->json('sections.0.persistent_id'));
});

test('a separate destination stays a separate task', function () {
    $palawan = DestinationModel::factory()->create(['name' => 'Palawan']);
    HotelModel::factory()->create(['hotel_name' => 'Palawan Cliff Hotel', 'destination_id' => $palawan->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);

    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $token = $first->json('session_token');
    $this->postJson('/chat', ['message' => 'hotels in Palawan', 'session_token' => $token])->assertOk();

    $tasks = ChatSession::where('session_token', $token)->firstOrFail()
        ->metadata['conversation_v2']['dialogue']['tasks'];

    expect($tasks)->toHaveCount(2);
});

test('ceiling wording all resolves to the same amount and unit', function (string $phrase) {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay '.$phrase]);

    $response->assertOk();
    expect($response->json('sections.0.constraints.max_price'))->toBe(3000);
    expect($response->json('sections.0.constraints.budget_mode'))->toBe('ceiling');
})->with([
    'under' => ['under 3000'],
    'within' => ['within 3000'],
    'up to' => ['up to 3,000'],
    'at most' => ['at most ₱3000'],
    'no more than' => ['no more than PHP 3000'],
    'hanggang' => ['hanggang 3k lang'],
    'maximum' => ['maximum of 3000'],
]);

test('a count after an operator is never read as money', function (string $phrase) {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay '.$phrase]);

    $response->assertOk();
    expect($response->json('sections.0.constraints.max_price'))->toBeNull();
})->with([
    'guests' => ['for max 4 guests'],
    'nights' => ['for up to 2 nights'],
    'distance' => ['within 5 km of the beach'],
]);

test('a question carries a deterministic control built from the catalog', function () {
    $response = $this->postJson('/chat', ['message' => 'help me plan a trip']);

    $response->assertOk()
        ->assertJsonPath('clarification.field', 'destination')
        ->assertJsonPath('clarification.control.type', 'select')
        ->assertJsonPath('clarification.control.fields.0.name', 'destination')
        ->assertJsonPath('clarification.control.fields.0.options.0.label', 'Boracay');

    // The option value is a server-side id, not a label the client invents.
    expect($response->json('clarification.control.fields.0.options.0.value'))->toBe((string) $this->destination->id);
    expect($response->json('clarification.control.manual_hint'))->toContain('type');
});

test('a date question offers labelled check-in and check-out inputs with a valid range', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);

    $first->assertOk()
        ->assertJsonPath('clarification.field', 'dates')
        ->assertJsonPath('clarification.control.type', 'date_range')
        ->assertJsonPath('clarification.control.fields.0.label', 'Check-in')
        ->assertJsonPath('clarification.control.fields.1.label', 'Check-out')
        ->assertJsonPath('clarification.control.skip.label', 'Dates undecided');

    expect($first->json('clarification.control.fields.0.min'))->toBe(now()->toDateString());
    expect($first->json('clarification.control.fields.0.input'))->toBe('date');
});

test('a structured answer is accepted and recorded as a readable message', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'session_token' => $token,
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => ['check_in' => '2026-11-05', 'check_out' => '2026-11-08'],
    ]);

    $second->assertOk();
    expect($second->json('sections.0.constraints.check_in_date'))->toBe('2026-11-05');
    expect($second->json('sections.0.constraints.check_out_date'))->toBe('2026-11-08');

    $session = ChatSession::where('session_token', $token)->firstOrFail();
    expect($session->messages()->where('sender', 'user')->latest('id')->first()->message)
        ->toBe('2026-11-05 to 2026-11-08');
});

test('a structured answer is validated server-side and reports field errors', function (array $answer, string $field) {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);
    $token = $first->json('session_token');
    $session = ChatSession::where('session_token', $token)->firstOrFail();
    $before = $session->messages()->count();

    $this->postJson('/chat', [
        'session_token' => $token,
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => $answer,
    ])->assertStatus(422)->assertJsonPath('status', 'invalid_answer')->assertJsonStructure(['errors' => [$field]]);

    // A rejected answer stores nothing and leaves the question standing.
    expect($session->messages()->count())->toBe($before);
})->with([
    'past date' => [['check_in' => '2020-01-01', 'check_out' => '2020-01-05'], 'check_in'],
    'malformed' => [['check_in' => '05/11/2026', 'check_out' => '08/11/2026'], 'check_in'],
    'reversed' => [['check_in' => '2026-11-08', 'check_out' => '2026-11-05'], 'check_out'],
    'missing' => [['check_in' => '2026-11-05'], 'check_out'],
]);

test('a tampered option value is rejected', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);

    $this->postJson('/chat', [
        'session_token' => $first->json('session_token'),
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => ['destination' => '99999'],
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['destination']]);
});

test('a structured skip uses the offered skip value', function () {
    $first = $this->postJson('/chat', ['message' => 'check room availability in Boracay']);

    $this->postJson('/chat', [
        'session_token' => $first->json('session_token'),
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => ['skip' => true],
    ])->assertOk()->assertJsonPath('clarification', null)
        ->assertJsonPath('retrieved_rooms.0.id', $this->room->id);
});

test('a stale structured answer changes nothing', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $token = $first->json('session_token');
    $this->postJson('/chat', ['message' => 'Boracay', 'session_token' => $token])->assertOk();

    $session = ChatSession::where('session_token', $token)->firstOrFail();
    $before = $session->messages()->count();

    $this->postJson('/chat', [
        'session_token' => $token,
        'question_id' => $first->json('clarification.id'),
        'question_revision' => $first->json('clarification.revision'),
        'answer' => ['destination' => (string) $this->destination->id],
    ])->assertStatus(409)->assertJsonPath('status', 'stale_question');

    expect($session->messages()->count())->toBe($before);
});

test('an over-limit message is rejected with a usable error', function () {
    $this->postJson('/chat', ['message' => str_repeat('a', 1001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

test('an overlapping turn for the same session is refused without processing it twice', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $token = $first->json('session_token');
    $session = ChatSession::where('session_token', $token)->firstOrFail();
    $before = $session->messages()->count();

    // The turn lock lives in the database cache store, which is a real lock
    // provider, so this is the same lock an Apache worker would contend for.
    // A multi-worker run is still needed to prove the end-to-end property.
    $lock = Cache::lock('chat-turn:'.$session->id, 300);
    expect($lock->get())->toBeTrue();

    try {
        $this->postJson('/chat', ['message' => 'activities in Boracay', 'session_token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('status', 'busy');
    } finally {
        $lock->release();
    }

    expect($session->messages()->count())->toBe($before);

    // Once released, the same session works again.
    $this->postJson('/chat', ['message' => 'activities in Boracay', 'session_token' => $token])->assertOk();
});

test('a different session is unaffected by another session being busy', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();

    $lock = Cache::lock('chat-turn:'.$session->id, 300);
    expect($lock->get())->toBeTrue();

    try {
        $this->postJson('/chat', ['message' => 'hotels in Boracay'])->assertOk();
    } finally {
        $lock->release();
    }
});

test('a trip summary reports known fields with per-field edit prompts', function () {
    $first = $this->postJson('/chat', ['message' => 'plan a 3 day trip to Boracay for 2 pax']);

    $first->assertOk();
    $summary = $first->json('trip_summary');

    expect($summary['heading'])->toContain('Boracay');
    $fields = collect($summary['items']);
    expect($fields->pluck('field'))->toContain('destination', 'pax', 'days');
    expect($fields->firstWhere('field', 'pax')['value'])->toBe('2');

    // Each edit targets one field in one trip scope and is phrased to be
    // completed by the user rather than submitted as-is.
    foreach ($summary['items'] as $item) {
        expect($item['edit_prompt'])->toStartWith('Change the ')
            ->and($item['edit_prompt'])->toEndWith(' to ');
        // Every edit but the destination itself names the trip scope.
        if ($item['field'] !== 'destination') {
            expect($item['edit_prompt'])->toContain('Boracay');
        }
    }
});

test('an unknown field is left out of the summary', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay']);

    $response->assertOk();
    expect(collect($response->json('trip_summary.items') ?? [])->pluck('field'))
        ->not->toContain('budget');
});
