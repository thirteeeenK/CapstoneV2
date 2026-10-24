<?php

use App\Models\ActivityModel;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.chatbot.guided_conversation_enabled' => true, 'services.chatbot.planner_enabled' => false]);
    $this->travelTo(now()->setDate(2026, 10, 21));
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]]),
    ]);
    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id, 'hotel_description' => 'A relaxing beach stay.', 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_price' => 2500, 'is_shown' => true, 'embedding' => unitVectorString(0), 'max_occupancy' => 4]);
    $this->activity = ActivityModel::factory()->create(['destination_id' => $this->destination->id, 'activity_level' => 'Relaxing', 'description' => 'A relaxing experience.', 'is_shown' => true, 'embedding' => unitVectorString(0), 'rate' => '900']);
});

function tripDetailsPayload(TestResponse $response, array $values): array
{
    $form = $response->json('trip_details_form');

    return ['session_token' => $response->json('session_token'), 'form_id' => $form['id'], 'form_revision' => $form['revision'], 'scope_id' => $form['scope_id'], 'trip_details' => $values];
}

test('dates must match the chosen duration instead of silently replacing it', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2,
        'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-04', 'days' => 12]))
        ->assertUnprocessable()->assertJsonValidationErrors(['trip_details.check_out_date' => 'the trip is 12 days, 11 nights']);

    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2,
        'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-04', 'days' => 4]));
    $response->assertOk()->assertJsonPath('trip_details_form.values.days', 4)->assertJsonPath('trip_details_form.values.nights', 3)
        ->assertJsonPath('itinerary_suggestion.accommodation.hotel_name', $this->hotel->hotel_name)
        ->assertJsonPath('itinerary_suggestion.accommodation.nights', 3)
        ->assertJsonPath('itinerary_suggestion.days.3.date', '2026-11-04');
});

test('an exact date itinerary form requires dates and an undecided itinerary requires duration', function (array $input, string $error) {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', tripDetailsPayload($first, $input + ['destination_id' => $this->destination->id]))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'exact dates missing' => [['dates_undecided' => false], 'trip_details.check_in_date'],
    'undecided without duration' => [['dates_undecided' => true], 'trip_details.days'],
    'reversed' => [['check_in_date' => '2026-11-04', 'check_out_date' => '2026-11-01'], 'trip_details.check_out_date'],
]);

test('undated complete itineraries retain accommodation but activities-only itineraries do not', function (string $query, bool $stay) {
    $first = $this->postJson('/chat', ['message' => $query]);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id,
        'pax' => 2, 'days' => 3, 'dates_undecided' => true]));
    $response->assertOk()->assertJsonPath('itinerary_suggestion.duration_nights', 2);
    if ($stay) {
        $response->assertJsonPath('itinerary_suggestion.accommodation.hotel_name', $this->hotel->hotel_name)
            ->assertJsonPath('itinerary_suggestion.accommodation.check_in_date', null)
            ->assertJsonPath('itinerary_suggestion.accommodation.total', 5000)
            ->assertJsonPath('itinerary_suggestion.accommodation.availability_status', 'not_checked');
    } else {
        $response->assertJsonPath('itinerary_suggestion.accommodation', null);
        expect(array_column($response->json('itinerary_suggestion.choices'), 'type'))->not->toContain('room');
    }
})->with([['help me plan a trip', true], ['plan an activities-only itinerary', false], ['plan a trip, I already booked a hotel', false]]);

test('a missing room does not discard a draft activity itinerary', function () {
    RoomType::query()->update(['is_shown' => false]);
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id,
        'pax' => 2, 'days' => 3, 'dates_undecided' => true]));
    $response->assertOk()->assertJsonPath('itinerary_suggestion.accommodation', null)
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('itinerary_suggestion.accommodation_notice'))->toContain('No matching room');
});

test('date-derived duration remains correct across leap days and mode changes', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $dated = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2,
        'check_in_date' => '2028-02-28', 'check_out_date' => '2028-03-01']));
    $dated->assertOk()->assertJsonPath('trip_details_form.values.days', 3)->assertJsonPath('trip_details_form.values.nights', 2);
    $undated = $this->postJson('/chat', tripDetailsPayload($dated, ['dates_undecided' => true]));
    $undated->assertOk()->assertJsonPath('itinerary_suggestion.start_date', null)
        ->assertJsonPath('itinerary_suggestion.duration_days', 3)
        ->assertJsonPath('itinerary_suggestion.accommodation.availability_status', 'not_checked');
    $this->postJson('/chat', tripDetailsPayload($undated, ['dates_undecided' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors('trip_details.check_in_date');
});

test('dated itineraries keep activities when no room fits and honor room exclusions', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->hotel->update(['hotel_description' => 'A hotel with a private swimming pool.']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2,
        'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-04', 'notes' => 'no pool']));
    $response->assertOk()->assertJsonPath('itinerary_suggestion.accommodation', null)
        ->assertJsonPath('itinerary_suggestion.days.0.date', '2026-11-01')
        ->assertJsonPath('retrieved_activities.0.id', $this->activity->id);
    expect($response->json('itinerary_suggestion.accommodation_notice'))->not->toBeNull();
});

test('one trip details submission unlocks both categories and echoes readable context', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels and activities']);
    $first->assertOk()->assertJsonPath('trip_details_form.version', 1);
    $second = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'preferences' => ['relaxing']]));
    $second->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id)
        ->assertJsonPath('clarification', null)->assertJsonPath('trip_details_form.values.pax', 2);
    expect($second->json('user_message'))->toContain('Boracay', '2 travelers', 'relaxing');
    $this->getJson('/chat/history?session_token='.$first->json('session_token'))->assertOk();
    $state = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail()->metadata['conversation_v2']['dialogue'];
    expect($state['trip_details_form']['id'])->toBe($second->json('trip_details_form.id'));
});

test('a structured undated itinerary keeps its duration and can be saved later', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 3, 'dates_undecided' => true]));
    $response->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('itinerary_suggestion.kind', 'chatbot_draft')
        ->assertJsonPath('itinerary_suggestion.start_date', null)->assertJsonCount(3, 'itinerary_suggestion.days');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openweather') || str_contains($request->url(), 'routing'));
    $id = $response->json('itinerary_suggestion.id');
    $session = ChatSession::where('session_token', $response->json('session_token'))->firstOrFail();
    expect($session->messages()->where('sender', 'bot')->latest('id')->first()->context_data['itinerary_suggestions'][$id]['id'])->toBe($id);
});

test('typed corrections retire the old trip form without accepting stale writes', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels and activities']);
    $this->postJson('/chat', ['message' => 'Boracay', 'session_token' => $first->json('session_token')])->assertOk();
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    $before = $session->messages()->count();
    $this->postJson('/chat', tripDetailsPayload($first, ['pax' => 4]))->assertStatus(409)->assertJsonPath('status', 'stale_form');
    expect($session->messages()->count())->toBe($before);
});

test('invalid or forged trip fields do not partially update dialogue', function (array $input) {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    $state = $session->metadata;
    $this->postJson('/chat', tripDetailsPayload($first, $input))->assertUnprocessable();
    expect($session->fresh()->metadata)->toBe($state)->and($session->messages()->count())->toBe(2);
})->with([
    'invalid count' => [['pax' => 0, 'destination_id' => 999999]],
    'nested preference' => [['preferences' => [['arbitrary' => 'value']]]],
    'unknown field' => [['user_id' => 123]],
    'incomplete dates' => [['check_in_date' => '2026-11-01']],
    'too long' => [['days' => 31]],
]);

test('form period and basis remain separate and preserve omitted context', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax under PHP 3000 per person']);
    $second = $this->postJson('/chat', tripDetailsPayload($first, ['budget_period' => 'per_night']));
    $second->assertOk()->assertJsonPath('trip_details_form.values.budget_basis', 'per_person')->assertJsonPath('trip_details_form.values.budget_period', 'per_night')
        ->assertJsonPath('trip_details_form.values.max_price', 3000)->assertJsonPath('trip_details_form.values.pax', 2);
});

test('form scope cannot be forged', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $payload = tripDetailsPayload($first, ['pax' => 4]);
    $payload['scope_id'] = 'another-trip';
    $this->postJson('/chat', $payload)->assertStatus(409);
});

test('a trip details request cannot also contain a typed message', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels']);
    $this->postJson('/chat', tripDetailsPayload($first, ['pax' => 2]) + ['message' => 'override'])->assertUnprocessable();
});

test('one combined-budget form creates an undated itinerary and retains both result categories', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels and activities']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 3,
        'dates_undecided' => true, 'max_price' => 20000, 'budget_basis' => 'trip_total', 'budget_period' => 'total', 'budget_coverage' => 'all_inclusive']));
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id)
        ->assertJsonPath('itinerary_suggestion.kind', 'chatbot_draft')->assertJsonPath('clarification', null);
    expect(substr_count($response->json('reply'), '?'))->toBeLessThanOrEqual(1);
});

test('an activity-only budget does not filter the requested hotels', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels and activities in Boracay']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['pax' => 2, 'max_price' => 1000, 'budget_basis' => 'per_person', 'budget_coverage' => 'activities']));
    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonPath('retrieved_activities.0.id', $this->activity->id)
        ->assertJsonPath('clarification', null);
});

test('a form can explicitly replace a per-person budget with a group nightly budget', function () {
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax under PHP 3000 per person']);
    $this->postJson('/chat', tripDetailsPayload($first, ['budget_basis' => 'per_night']))->assertOk()
        ->assertJsonPath('trip_details_form.values.budget_basis', 'per_night')->assertJsonPath('trip_details_form.values.budget_period', 'per_night');
});

test('an undated one-day draft with a budget does not invent a paid hotel night', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 1, 'dates_undecided' => true,
        'max_price' => 10000, 'budget_basis' => 'trip_total']))->assertOk()->assertJsonPath('clarification', null)->assertJsonCount(1, 'itinerary_suggestion.days');
});

test('saved draft choices respect exclusions applied to activity cards', function () {
    $excluded = ActivityModel::factory()->create(['activity_name' => 'Party Boat Cruise', 'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'days' => 3, 'dates_undecided' => true, 'notes' => 'without Party Boat Cruise']));
    $response->assertOk();
    expect(array_column($response->json('itinerary_suggestion.choices'), 'id'))->not->toContain($excluded->id);
});

test('a dated itinerary compares an activity budget against activities rather than the hotel', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2,
        'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-03', 'max_price' => 1800, 'budget_basis' => 'trip_total', 'budget_coverage' => 'activities']));
    $response->assertOk()->assertJsonPath('itinerary.budget_check.scope', 'activities')->assertJsonPath('itinerary.budget_check.status', 'within');
    expect($response->json('itinerary.grand_total'))->toBeGreaterThan(1800)->and($response->json('reply'))->toContain('Activity budget check', 'extra');
});

test('clearing form notes removes only the exclusions those notes introduced', function () {
    $first = $this->postJson('/chat', ['message' => 'activities in Boracay']);
    $second = $this->postJson('/chat', tripDetailsPayload($first, ['notes' => 'no swimming pool']));
    $second->assertOk();
    $third = $this->postJson('/chat', tripDetailsPayload($second, ['notes' => null]));
    $third->assertOk();
    expect($third->json('sections.0.constraints.excluded_terms'))->toBe([]);
});

test('moving only the arrival moves the whole trip and keeps its duration', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $second = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-04']));
    $second->assertOk();
    $this->postJson('/chat', tripDetailsPayload($second, ['check_in_date' => '2026-11-02']))->assertOk()
        ->assertJsonPath('trip_details_form.values.check_out_date', '2026-11-05')->assertJsonPath('trip_details_form.values.days', 4);
});

test('an arrival and a duration are enough; the departure follows from them', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 6, 'check_in_date' => '2026-10-26']))->assertOk()
        ->assertJsonPath('trip_details_form.values.check_out_date', '2026-10-31')->assertJsonPath('itinerary_suggestion.duration_days', 6)
        ->assertJsonPath('itinerary_suggestion.days.5.date', '2026-10-31');
});

test('arrivals follow the two-day Manila lead policy and a dated trip needs one night', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    expect($first->json('trip_details_form.minimum_arrival'))->toBe('2026-10-23');
    $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'days' => 3, 'check_in_date' => '2026-10-22']))
        ->assertUnprocessable()->assertJsonValidationErrors(['trip_details.check_in_date' => 'two calendar days ahead']);
    $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'days' => 1, 'check_in_date' => '2026-11-01']))
        ->assertUnprocessable()->assertJsonValidationErrors(['trip_details.days' => 'at least 2 days']);
});

test('a contradictory per-night amount for the whole trip is rejected', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'days' => 3, 'dates_undecided' => true,
        'max_price' => 5000, 'budget_basis' => 'per_night', 'budget_period' => 'total']))
        ->assertUnprocessable()->assertJsonValidationErrors(['trip_details.budget_period' => 'cannot also cover the whole trip']);
});

test('excluding accommodation drafts activities only', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 3, 'dates_undecided' => true, 'include_accommodation' => false]))
        ->assertOk()->assertJsonPath('itinerary_suggestion.accommodation', null)->assertJsonPath('trip_details_form.values.include_accommodation', false);
});

test('native form strings are normalized into numeric context values', function () {
    $first = $this->postJson('/chat', ['message' => 'activities in Boracay']);
    $this->postJson('/chat', tripDetailsPayload($first, ['pax' => '2', 'max_price' => '1000', 'budget_basis' => 'per_person']))->assertOk()
        ->assertJsonPath('trip_details_form.values.pax', 2)->assertJsonPath('trip_details_form.values.max_price', 1000);
});

test('a trip form changes its selected destination scope without overwriting another trip', function () {
    $other = DestinationModel::factory()->create(['name' => 'El Nido']);
    ActivityModel::factory()->create(['destination_id' => $other->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay for 2 pax']);
    $second = $this->postJson('/chat', ['message' => 'activities in El Nido', 'session_token' => $first->json('session_token')]);
    $second->assertOk()->assertJsonPath('trip_details_form.values.destination_id', $other->id);
    $third = $this->postJson('/chat', tripDetailsPayload($second, ['pax' => 4]));
    $third->assertOk();
    $state = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail()->metadata['conversation_v2']['dialogue'];
    $boracay = collect($state['tasks'])->firstWhere('constraints.destination_id', $this->destination->id);
    $elNido = collect($state['tasks'])->firstWhere('constraints.destination_id', $other->id);
    expect($boracay['constraints']['pax'])->toBe(2)->and($elNido['constraints']['pax'])->toBe(4);
});

test('date and duration errors read as plain guidance without internal field paths', function (array $input, string $key, string $message) {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $response = $this->postJson('/chat', tripDetailsPayload($first, $input + ['destination_id' => $this->destination->id]));
    $response->assertUnprocessable()->assertJsonValidationErrors([$key => $message]);
    expect(str_contains((string) $response->json('errors.'.$key.'.0'), 'trip details'))->toBeFalse();
})->with([
    'missing departure' => [['check_in_date' => '2026-11-04'], 'trip_details.check_out_date', 'Choose a departure date, or choose "Decide later".'],
    'reversed' => [['check_in_date' => '2026-11-04', 'check_out_date' => '2026-11-01'], 'trip_details.check_out_date', 'Departure must be after arrival.'],
    'past arrival' => [['check_in_date' => '2020-01-01', 'check_out_date' => '2020-01-05'], 'trip_details.check_in_date', 'at least two calendar days ahead in Philippine time'],
    'beyond thirty days' => [['check_in_date' => '2026-11-01', 'check_out_date' => '2026-12-20'], 'trip_details.check_out_date', 'Those dates cover 50 days. Plan up to 30 days at a time.'],
    'undecided without duration' => [['dates_undecided' => true], 'trip_details.days', 'Enter how many days the trip should last.'],
]);

test('changing only the duration updates the itinerary without rerunning an answered activities search', function () {
    $first = $this->postJson('/chat', ['message' => 'activities in Boracay']);
    $plan = $this->postJson('/chat', ['message' => 'help me plan a trip to Boracay', 'session_token' => $first->json('session_token')]);
    $three = $this->postJson('/chat', tripDetailsPayload($plan, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 3, 'dates_undecided' => true]));
    $three->assertOk();
    $six = $this->postJson('/chat', tripDetailsPayload($three, ['days' => 6]));

    $six->assertOk()->assertJsonPath('itinerary_suggestion.duration_days', 6)->assertJsonPath('itinerary_suggestion.duration_nights', 5);
    expect(collect($six->json('sections'))->pluck('type')->all())->toBe(['ITINERARY_QUERY'])
        ->and($six->json('trip_details_form.values.pax'))->toBe(2);
});

test('a six-day draft is not cut to the three preview cards and an unchanged redraft keeps its identity', function () {
    foreach (range(1, 5) as $i) {
        ActivityModel::factory()->create(['destination_id' => $this->destination->id, 'activity_level' => 'Relaxing', 'description' => 'A relaxing experience '.$i.'.', 'is_shown' => true, 'embedding' => unitVectorString(0), 'rate' => '900']);
    }
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $details = ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 6, 'dates_undecided' => true];
    $draft = $this->postJson('/chat', tripDetailsPayload($first, $details));

    $activities = collect($draft->json('itinerary_suggestion.choices'))->where('type', 'activity');
    expect($activities->count())->toBeGreaterThan(3)
        ->and($draft->json('reply'))->not->toContain('do not have enough verified activities');

    $again = $this->postJson('/chat', tripDetailsPayload($draft, ['notes' => null]));
    expect($again->json('itinerary_suggestion.id'))->toBe($draft->json('itinerary_suggestion.id'));
});

test('a draft without travelers asks for them instead of calling the price stale', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $draft = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'days' => 3, 'dates_undecided' => true]));

    $draft->assertOk()->assertJsonPath('itinerary_suggestion.accommodation.nightly_rate', null);
    expect($draft->json('reply'))->toContain('Add the number of travelers to calculate the stay estimate');
});

test('a duration-only edit keeps the drafted stay while other changes search again', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $three = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 3, 'dates_undecided' => true]));
    $kept = $three->json('itinerary_suggestion.accommodation.room_id');
    expect($kept)->not->toBeNull();

    // A closer semantic match now exists; it must not displace the stay on a length-only edit.
    RoomType::whereKey($kept)->update(['embedding' => unitVectorString(5)]);
    $better = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_price' => 2000, 'is_shown' => true, 'embedding' => unitVectorString(0), 'max_occupancy' => 4]);

    $six = $this->postJson('/chat', tripDetailsPayload($three, ['days' => 6]));
    $six->assertOk()->assertJsonPath('itinerary_suggestion.accommodation.room_id', $kept)->assertJsonPath('itinerary_suggestion.accommodation.nights', 5);

    $four = $this->postJson('/chat', tripDetailsPayload($six, ['pax' => 3]));
    $four->assertOk()->assertJsonPath('itinerary_suggestion.accommodation.room_id', $better->id);
});

test('a draft shows planned activity details and one cost breakdown checked against the budget', function () {
    $first = $this->postJson('/chat', ['message' => 'help me plan a trip']);
    $draft = $this->postJson('/chat', tripDetailsPayload($first, ['destination_id' => $this->destination->id, 'pax' => 2, 'days' => 3, 'dates_undecided' => true,
        'max_price' => 5500, 'budget_basis' => 'trip_total', 'budget_period' => 'total', 'budget_coverage' => 'all_inclusive']));

    $cost = $draft->json('itinerary_suggestion.cost');
    $activity = collect($draft->json('itinerary_suggestion.choices'))->firstWhere('type', 'activity');
    expect($activity['detail']['price'])->not->toBeEmpty()
        ->and($cost['stay'])->toEqual(5000)->and($cost['total'])->toEqual($cost['stay'] + $cost['activities'])
        ->and($cost['status'])->toBe('over')->and($cost['summary'])->toContain('over your ₱5,500.00 trip budget')
        ->and($draft->json('reply'))->toContain('Estimated total');
});
