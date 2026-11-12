<?php

use App\Models\ActivityModel;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.chatbot.guided_conversation_enabled' => true, 'services.chatbot.planner_enabled' => false]);
    $this->travelTo(now()->setDate(2026, 10, 21));
    Http::preventStrayRequests();
    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create(['hotel_name' => 'Palm Beach Resort', 'destination_id' => $this->destination->id,
        'hotel_description' => 'A quiet family resort beside the shore.', 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'room_name' => 'Palm Deluxe Suite', 'base_price' => 2500, 'base_occupancy' => 2, 'max_occupancy' => 4, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
});

function fakeChatModel(string $answer): void
{
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $answer]]]]]]),
    ]);
}

test('a catalog answer is written by the model from the retrieved records and shown without a heading', function () {
    fakeChatModel('**Palm Beach Resort** is a quiet family resort beside the shore, so it suits a relaxed stay. Would you like to see its rooms?');

    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay']);

    $response->assertOk()->assertJsonPath('sections.0.composition', 'validated')->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
    expect($response->json('reply'))->toContain('quiet family resort beside the shore')->not->toContain('###')->not->toContain('I found 1 verified option');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'generateContent')
        && str_contains(json_encode($request['contents']), 'DATABASE RESULTS')
        && str_contains(json_encode($request['contents']), 'A quiet family resort beside the shore.'));
});

test('the answer call receives the earlier turns of the conversation', function () {
    fakeChatModel('**Palm Beach Resort** is the option I have for Boracay.');
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $this->postJson('/chat', ['message' => 'hotels in Boracay for a family', 'session_token' => $first->json('session_token')])->assertOk();

    $requests = Http::recorded(fn ($request) => str_contains($request->url(), 'generateContent'));
    $last = $requests->last()[0];
    expect(count($last['contents']))->toBeGreaterThan(1)
        ->and(json_encode($last['contents']))->toContain('hotels in Boracay', 'is the option I have for Boracay.');
});

test('sentences the grounding checks cannot verify are removed and the rest of the answer is kept', function () {
    fakeChatModel('**Palm Beach Resort** is a quiet family resort beside the shore. Rooms there cost ₱999 per night. I have booked it for you. **Coral Sands Hotel** is also nice.');

    $reply = $this->postJson('/chat', ['message' => 'hotels in Boracay'])->assertOk()->json('reply');

    expect($reply)->toContain('quiet family resort beside the shore')
        ->not->toContain('₱999')->not->toContain('booked it')->not->toContain('Coral Sands');
});

test('a reply that is not prose falls back to the deterministic answer', function () {
    fakeChatModel('{"blocks":[]}');

    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay']);

    $response->assertOk()->assertJsonPath('sections.0.composition', 'deterministic');
    expect($response->json('reply'))->toContain('I found 1 verified option');
});

test('a plain search offers the trip form without opening it and asks its question in prose with chips', function () {
    fakeChatModel('**Palm Beach Resort** is the option I have for Boracay.');

    $search = $this->postJson('/chat', ['message' => 'hotels in Boracay']);
    $search->assertOk()->assertJsonPath('clarification', null)
        ->assertJsonPath('trip_details_form.on_request', true)->assertJsonPath('trip_details_form.expanded', false);

    $question = $this->postJson('/chat', ['message' => 'show me hotels']);
    $question->assertOk()->assertJsonPath('clarification.field', 'destination')->assertJsonPath('clarification.inline_control', false)
        ->assertJsonPath('clarification.options.0.label', 'Boracay')->assertJsonPath('trip_details_form.expanded', false);
    expect(collect($question->json('suggested_actions'))->pluck('label'))->toContain('Boracay');
});

test('a budget with no stated basis is applied and the user can correct the reading on the next turn', function () {
    fakeChatModel('{"blocks":[]}');

    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay under PHP 3000 for 2 pax']);
    $first->assertOk()->assertJsonPath('clarification', null)->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
    expect($first->json('reply'))->toContain('I read your budget as ₱3,000 per night');

    $this->postJson('/chat', ['message' => 'per person', 'session_token' => $first->json('session_token')])->assertOk()
        ->assertJsonPath('sections.0.budget_basis', 'per_person')->assertJsonPath('clarification.field', 'budget_period');
});

test('an itinerary keeps its trip form available but collapsed', function () {
    fakeChatModel('{"blocks":[]}');

    $this->postJson('/chat', ['message' => 'help me plan a trip'])->assertOk()
        ->assertJsonPath('trip_details_form.on_request', false)->assertJsonPath('trip_details_form.expanded', false);
});

test('a requested feature that no option lists is reported instead of showing options without it', function () {
    fakeChatModel('**Palm Beach Resort** is the option I have for Boracay.');

    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay with a swimming pool']);

    $response->assertOk()->assertJsonPath('sections.0.outcome.reason', 'requested_feature_unverified');
    expect($response->json('retrieved_hotels'))->toBeNull()
        ->and($response->json('reply'))->toContain('could not find an option in Boracay that lists pool');
    expect(collect($response->json('suggested_actions'))->pluck('prompt'))->toContain('hotels in Boracay');
});

/** Routes the turn-understanding call and the answer call to different fakes. */
function fakeInterpretedTurn(array $interpretation, string $answer): void
{
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => fn ($request) => Http::response(['candidates' => [['content' => ['parts' => [['text' => ($request['generationConfig']['responseMimeType'] ?? null) === 'application/json' ? json_encode($interpretation) : $answer]]]]]]),
    ]);
}

test('a question about the options on screen is answered from their records without searching again', function () {
    $this->hotel->update(['featured_amenities' => ['Infinity Pool', 'Free WiFi']]);
    fakeInterpretedTurn(['mode' => 'about_shown', 'standalone_query' => ''], 'Yes, **Palm Beach Resort** lists an Infinity Pool among its amenities.');
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);

    $response = $this->postJson('/chat', ['message' => 'does it have a pool?', 'session_token' => $first->json('session_token')]);

    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id)->assertJsonMissingPath('sections');
    expect($response->json('reply'))->toContain('lists an Infinity Pool');
    $answerCall = Http::recorded(fn ($request) => str_contains($request->url(), 'generateContent') && ! isset($request['generationConfig']['responseMimeType']))->last()[0];
    expect(json_encode($answerCall['contents']))->toContain('FULL DETAILS OF THESE OPTIONS', 'Infinity Pool', 'does it have a pool?');
});

test('an elliptical request the rules cannot place is searched from the model rewrite', function () {
    $elNido = DestinationModel::factory()->create(['name' => 'El Nido']);
    $lagoon = HotelModel::factory()->create(['hotel_name' => 'Lagoon View Inn', 'destination_id' => $elNido->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $lagoon->id, 'base_price' => 1800, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    fakeInterpretedTurn(['mode' => 'search', 'standalone_query' => 'hotels in El Nido'], '{"blocks":[]}');
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);

    $response = $this->postJson('/chat', ['message' => 'anything further south, like Palawan?', 'session_token' => $first->json('session_token')]);

    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $lagoon->id);
});

test('an unreadable interpretation leaves the rule-based routing in charge', function () {
    fakeChatModel('{"blocks":[]}');
    $first = $this->postJson('/chat', ['message' => 'hotels in Boracay']);

    $this->postJson('/chat', ['message' => 'activities in Boracay', 'session_token' => $first->json('session_token')])
        ->assertOk()->assertJsonPath('sections.0.type', 'ACTIVITY_SEARCH');
});

test('a question asked instead of an answer is answered rather than met with the same question again', function () {
    fakeChatModel('Happy to help. Boracay is one of the places I can plan for you.');
    $first = $this->postJson('/chat', ['message' => 'plan a trip to Boracay']);
    $first->assertOk()->assertJsonPath('clarification.field', 'days');

    $response = $this->postJson('/chat', ['message' => 'what do you recommend?', 'session_token' => $first->json('session_token')]);

    $response->assertOk();
    expect($response->json('clarification'))->toBeNull()
        ->and($response->json('reply'))->not->toContain('could not confirm')->not->toContain('How many days');
});

describe('price words beside other wishes', function () {
    beforeEach(function () {
        fakeChatModel('{"blocks":[]}');
        // Cheapest in the destination, but unrelated to the request (below the relevance floor).
        $this->hostel = HotelModel::factory()->create(['hotel_name' => 'Party Hostel', 'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(1)]);
        RoomType::factory()->create(['hotel_id' => $this->hostel->id, 'base_price' => 500, 'base_occupancy' => 2, 'max_occupancy' => 2, 'is_shown' => true, 'embedding' => unitVectorString(1)]);
        $this->cove = HotelModel::factory()->create(['hotel_name' => 'Quiet Cove Inn', 'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
        RoomType::factory()->create(['hotel_id' => $this->cove->id, 'base_price' => 4000, 'base_occupancy' => 2, 'max_occupancy' => 2, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    });

    test('relevant options are ranked by price instead of the cheapest unrelated one winning', function () {
        $names = collect($this->postJson('/chat', ['message' => 'affordable family hotels in Boracay'])->assertOk()->json('retrieved_hotels'))->pluck('hotel_name');

        expect($names->all())->toBe(['Palm Beach Resort', 'Quiet Cove Inn']);
    });

    test('a pure cheapest request still ranks the whole destination by price', function () {
        $this->postJson('/chat', ['message' => 'cheapest hotels in Boracay'])->assertOk()
            ->assertJsonPath('retrieved_hotels.0.hotel_name', 'Party Hostel');
    });

    test('a stated budget amount filters by relevance and is not read as a cheapest request', function () {
        $names = collect($this->postJson('/chat', ['message' => 'hotels in Boracay, my budget is 3000 per night'])->assertOk()->json('retrieved_hotels'))->pluck('hotel_name');

        expect($names)->toContain('Palm Beach Resort')->not->toContain('Party Hostel');
    });
});

test('a room with no stated maximum counts its base occupancy when matching a party size', function () {
    fakeChatModel('{"blocks":[]}');
    $lodge = HotelModel::factory()->create(['hotel_name' => 'Base Four Lodge', 'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $lodge->id, 'base_price' => 3000, 'base_occupancy' => 4, 'max_occupancy' => null, 'is_shown' => true, 'embedding' => unitVectorString(0)]);

    $names = collect($this->postJson('/chat', ['message' => 'hotels in Boracay for 4 pax'])->assertOk()->json('retrieved_hotels'))->pluck('hotel_name');

    expect($names)->toContain('Base Four Lodge');
});

describe('answers from a reviewed source', function () {
    beforeEach(function () {
        $this->faq = Faq::factory()->create([
            'question' => 'Can I cancel my booking and get a refund?',
            'answer' => 'Cancellations made at least 7 days before check-in receive a full refund. Later cancellations are not refundable.',
            'is_active' => true,
        ]);
    });

    test('a direct answer written from the source is placed above the reviewed text', function () {
        fakeChatModel('Yes, a full refund applies when you cancel at least 7 days before check-in.');

        $reply = $this->postJson('/chat', ['message' => 'Can I cancel my booking and get a refund?'])->assertOk()->json('reply');

        expect($reply)->toStartWith('Yes, a full refund applies')->toContain($this->faq->answer);
    });

    test('a lead with a figure the source does not contain is dropped and the reviewed text stands alone', function () {
        fakeChatModel('Yes, you can cancel up to 3 days before check-in for a full refund.');

        $reply = $this->postJson('/chat', ['message' => 'Can I cancel my booking and get a refund?'])->assertOk()->json('reply');

        expect($reply)->toStartWith('Cancellations made at least 7 days')->not->toContain('3 days');
    });
});

describe('the model reads the first message', function () {
    beforeEach(function () {
        $this->kayak = ActivityModel::factory()->create(['activity_name' => 'Family Kayak Tour', 'destination_id' => $this->destination->id, 'is_shown' => true, 'rate' => '900', 'embedding' => unitVectorString(0)]);
    });

    test('a request the keywords misplace is searched in the catalog the model read', function () {
        // "family" is a room keyword and nothing here says activity, tour or things to do.
        fakeInterpretedTurn(['mode' => 'search', 'looking_for' => ['activity'], 'standalone_query' => 'family fun in Boracay'], '{"blocks":[]}');

        $response = $this->postJson('/chat', ['message' => 'family fun in Boracay']);

        $response->assertOk()->assertJsonPath('sections.0.type', 'ACTIVITY_SEARCH')->assertJsonPath('retrieved_activities.0.id', $this->kayak->id);
        expect($response->json('retrieved_rooms'))->toBeNull()->and($response->json('retrieved_hotels'))->toBeNull();
    });

    test('a how-to question is answered in words instead of starting the itinerary questions', function () {
        fakeInterpretedTurn(['mode' => 'information', 'looking_for' => [], 'standalone_query' => ''], 'Tell me where you want to go and I can suggest places to stay and build an itinerary for you.');

        $response = $this->postJson('/chat', ['message' => 'how do I plan a trip to Boracay?']);

        $response->assertOk()->assertJsonMissingPath('sections');
        expect($response->json('clarification'))->toBeNull()
            ->and($response->json('trip_details_form'))->toBeNull()
            ->and($response->json('reply'))->toContain('build an itinerary for you');
        $answerCall = Http::recorded(fn ($request) => str_contains($request->url(), 'generateContent') && ! isset($request['generationConfig']['responseMimeType']))->last()[0];
        expect(json_encode($answerCall['contents']))->toContain('WHAT THE ASSISTANT CAN DO ON SUNNYTRIPS', 'Boracay');
    });

    test('a reading that agrees with the keywords changes nothing', function () {
        fakeInterpretedTurn(['mode' => 'search', 'looking_for' => ['room'], 'standalone_query' => 'hotels in Boracay'], '{"blocks":[]}');

        $this->postJson('/chat', ['message' => 'hotels in Boracay'])->assertOk()
            ->assertJsonPath('sections.0.type', 'HOTEL_SEARCH')->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
    });

    test('a named option keeps its exact handling even when the model calls the message a general question', function () {
        fakeInterpretedTurn(['mode' => 'information', 'looking_for' => [], 'standalone_query' => ''], 'General words.');

        $this->postJson('/chat', ['message' => 'what are the amenities of Palm Beach Resort?'])->assertOk()
            ->assertJsonPath('retrieved_hotels.0.id', $this->hotel->id);
    });
});

test('a short follow-up the rules handle is searched with the full rewrite of what the user wants', function () {
    $elNido = DestinationModel::factory()->create(['name' => 'El Nido']);
    $lagoon = HotelModel::factory()->create(['hotel_name' => 'Lagoon View Inn', 'destination_id' => $elNido->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $lagoon->id, 'base_price' => 1800, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    fakeInterpretedTurn(['mode' => 'search', 'looking_for' => ['hotel'], 'standalone_query' => 'quiet family hotels in El Nido'], '{"blocks":[]}');
    $first = $this->postJson('/chat', ['message' => 'quiet family hotels in Boracay']);

    $response = $this->postJson('/chat', ['message' => 'what about in El Nido?', 'session_token' => $first->json('session_token')]);

    $response->assertOk()->assertJsonPath('retrieved_hotels.0.id', $lagoon->id)->assertJsonPath('sections.0.query', 'quiet family hotels in El Nido');
    $embedded = Http::recorded(fn ($request) => str_contains($request->url(), 'embedContent'))->map(fn ($pair) => json_encode($pair[0]->data()));
    expect($embedded->contains(fn (string $body) => str_contains($body, 'quiet family hotels in El Nido')))->toBeTrue();
});

test('a hotel result offers natural next steps as chips', function () {
    fakeChatModel('{"blocks":[]}');

    $actions = collect($this->postJson('/chat', ['message' => 'hotels in Boracay'])->assertOk()->json('suggested_actions'));

    expect($actions->pluck('label')->all())->toContain('Things to do in Boracay', 'Rooms at Palm Beach Resort')
        ->and($actions->firstWhere('id', 'next-rooms')['prompt'])->toBe('Show me the rooms at Palm Beach Resort');
});

test('a hotel that exists but was not retrieved cannot be described, even without bold', function () {
    HotelModel::factory()->create(['hotel_name' => 'Coral Sands Hotel', 'destination_id' => DestinationModel::factory()->create(['name' => 'El Nido'])->id, 'is_shown' => true, 'embedding' => unitVectorString(3)]);
    fakeChatModel('**Palm Beach Resort** is a quiet family resort beside the shore. Coral Sands Hotel next door is livelier.');

    $reply = $this->postJson('/chat', ['message' => 'hotels in Boracay'])->assertOk()->json('reply');

    expect($reply)->toContain('quiet family resort')->not->toContain('Coral Sands');
});

test('a long conversation carries a summary of the earlier requests into the answer call', function () {
    fakeChatModel('**Palm Beach Resort** is the option I have for Boracay.');
    $first = $this->postJson('/chat', ['message' => 'we are a family of five who love quiet places']);
    $session = ChatSession::where('session_token', $first->json('session_token'))->firstOrFail();
    foreach (range(1, 12) as $i) {
        $session->messages()->create(['sender' => 'user', 'message' => "filler question {$i}"]);
        $session->messages()->create(['sender' => 'bot', 'message' => "filler answer {$i}"]);
    }

    $this->postJson('/chat', ['message' => 'hotels in Boracay', 'session_token' => $first->json('session_token')])->assertOk();

    $answerCall = Http::recorded(fn ($request) => str_contains($request->url(), 'generateContent') && ! isset($request['generationConfig']['responseMimeType']))->last()[0];
    expect(json_encode($answerCall['contents']))->toContain('Earlier in the conversation, the user asked', 'family of five who love quiet places');
});

test('a dates question keeps its picker behind a link', function () {
    fakeChatModel('{"blocks":[]}');

    $this->postJson('/chat', ['message' => 'check room availability in Boracay'])->assertOk()
        ->assertJsonPath('clarification.field', 'dates')->assertJsonPath('clarification.inline_control', true)->assertJsonPath('clarification.control_collapsed', true);
});
