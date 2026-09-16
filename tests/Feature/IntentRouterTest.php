<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Services\Chat\IntentRouter;
use Carbon\Carbon;

beforeEach(function () {
    DestinationModel::create(['name' => 'Boracay', 'description' => 'White beach', 'image' => null]);
    DestinationModel::create(['name' => 'Palawan', 'description' => 'Underground river', 'image' => null]);
    DestinationModel::create(['name' => 'El Nido', 'description' => 'Lagoons', 'image' => null]);
    DestinationModel::create(['name' => 'Cebu', 'description' => 'Whale sharks', 'image' => null]);
    $this->router = new IntentRouter;
});

test('classifies general greeting as GENERAL_TALK', function () {
    expect($this->router->classify('Hello there'))->toBe(IntentRouter::GENERAL_TALK);
    expect($this->router->classify('Kamusta po'))->toBe(IntentRouter::GENERAL_TALK);
    expect($this->router->classify('What is 2+2?'))->toBe(IntentRouter::GENERAL_TALK);
});

test('classifies room search correctly', function () {
    expect($this->router->classify('Find a beachfront room in Boracay'))->toBe(IntentRouter::ROOM_SEARCH);
    expect($this->router->classify('I need an ocean view villa'))->toBe(IntentRouter::ROOM_SEARCH);
    expect($this->router->classify('room for 2 with balcony'))->toBe(IntentRouter::ROOM_SEARCH);
});

test('classifies hotel search correctly', function () {
    expect($this->router->classify('Best resorts in Palawan'))->toBe(IntentRouter::HOTEL_SEARCH);
    expect($this->router->classify('Show me hotels in Cebu'))->toBe(IntentRouter::HOTEL_SEARCH);
});

test('classifies activity search correctly', function () {
    expect($this->router->classify('What tours are available in El Nido?'))->toBe(IntentRouter::ACTIVITY_SEARCH);
    expect($this->router->classify('island hopping activities'))->toBe(IntentRouter::ACTIVITY_SEARCH);
    expect($this->router->classify('things to do in Boracay'))->toBe(IntentRouter::ACTIVITY_SEARCH);
});

test('classifies itinerary query', function () {
    expect($this->router->classify('Plan a 3-day itinerary in Boracay'))->toBe(IntentRouter::ITINERARY_QUERY);
    expect($this->router->classify('Can you make a trip plan for Palawan?'))->toBe(IntentRouter::ITINERARY_QUERY);
});

test('classifies availability query', function () {
    expect($this->router->classify('Are there rooms available in El Nido from Aug 10 to Aug 13?'))->toBe(IntentRouter::AVAILABILITY_QUERY);
});

test('classifies weather query', function () {
    expect($this->router->classify('What is the weather in Boracay?'))->toBe(IntentRouter::WEATHER_QUERY);
    expect($this->router->classify('Will it rain this weekend in Palawan?'))->toBe(IntentRouter::WEATHER_QUERY);
});

test('classifies map query', function () {
    expect($this->router->classify('Where is Boracay?'))->toBe(IntentRouter::MAP_QUERY);
    expect($this->router->classify('How far is El Nido from Boracay?'))->toBe(IntentRouter::MAP_QUERY);
    expect($this->router->classify('gaano ako kalayo sa boracay?'))->toBe(IntentRouter::MAP_QUERY);
    expect($this->router->classify('layo ko sa el nido?'))->toBe(IntentRouter::MAP_QUERY);
    expect($this->router->classify('nasaan ako boracay?'))->toBe(IntentRouter::MAP_QUERY);
});

test('extracts pax count from query', function () {
    $c = $this->router->extractConstraints('Find a room for 4 pax in Boracay');
    expect($c['pax'])->toBe(4);
    expect($c['destination_name'])->toBe('Boracay');

    $c2 = $this->router->extractConstraints('Couples room in Palawan');
    expect($c2['pax'])->toBe(2);

    $c3 = $this->router->extractConstraints('Solo trip to Cebu');
    expect($c3['pax'])->toBe(1);
});

test('extracts max price from query', function () {
    $c = $this->router->extractConstraints('Room under ₱5000 in Boracay');
    expect($c['max_price'])->toBe(5000);

    $c2 = $this->router->extractConstraints('My budget is 3000 pesos');
    expect($c2['max_price'])->toBe(3000);
});

test('extracts destination name', function () {
    $c = $this->router->extractConstraints('Hotels in Palawan');
    expect($c['destination_name'])->toBe('Palawan');
    expect($c['destination_id'])->toBeGreaterThan(0);

    $c2 = $this->router->extractConstraints('What tours in El Nido?');
    expect($c2['destination_name'])->toBe('El Nido');
});

test('extracts nights from query', function () {
    $c = $this->router->extractConstraints('Stay for 3 nights in Boracay');
    expect($c['nights'])->toBe(3);

    $c2 = $this->router->extractConstraints('4 day trip to Cebu');
    expect($c2['days'])->toBe(4);
    expect($c2['nights'])->toBe(3);
});

test('extracts weekend date range', function () {
    $c = $this->router->extractConstraints('Room this weekend in Boracay');
    expect($c['check_in_date'])->not->toBeNull();
    expect($c['check_out_date'])->not->toBeNull();
    $in = Carbon::parse($c['check_in_date']);
    expect($in->isSaturday())->toBeTrue();
});

test('classifies booking status query', function () {
    expect($this->router->classify('What is the status of my booking?'))->toBe(IntentRouter::BOOKING_STATUS);
    expect($this->router->classify('Where is my booking?'))->toBe(IntentRouter::BOOKING_STATUS);
    expect($this->router->classify('Is my booking approved?'))->toBe(IntentRouter::BOOKING_STATUS);
    expect($this->router->classify('my reservation status'))->toBe(IntentRouter::BOOKING_STATUS);
    expect($this->router->classify('booking code ST-2026-ABCDE status'))->toBe(IntentRouter::BOOKING_STATUS);
    expect($this->router->classify('check my booking details'))->toBe(IntentRouter::BOOKING_STATUS);
});

test('booking searches are not mistaken for booking status', function () {
    expect($this->router->classify('Book a room in Boracay'))->toBe(IntentRouter::ROOM_SEARCH);
    expect($this->router->classify('I want to make a booking for 2 pax in Palawan'))->toBe(IntentRouter::ROOM_SEARCH);
    expect($this->router->classify('best resorts for honeymoon'))->toBe(IntentRouter::HOTEL_SEARCH);
});

test('extracts booking code from query', function () {
    expect($this->router->extractBookingCode('What is the status of booking code ST-2026-ABCDE?'))->toBe('ST-2026-ABCDE');
    expect($this->router->extractBookingCode('booking reference ABC123 status'))->toBe('ABC123');
    expect($this->router->extractBookingCode('what is the status of my booking'))->toBeNull();
});

test('classifies water-activity names as activity search', function () {
    expect($this->router->classify('banana boat'))->toBe(IntentRouter::ACTIVITY_SEARCH);
    expect($this->router->classify('how about banana boat'))->toBe(IntentRouter::ACTIVITY_SEARCH);
    expect($this->router->classify('parasailing'))->toBe(IntentRouter::ACTIVITY_SEARCH);
    expect($this->router->classify('difference of parasailing and paraw sailing'))->toBe(IntentRouter::ACTIVITY_SEARCH);
});

test('extracts partial activity names by token coverage', function () {
    ActivityModel::factory()->create(['activity_name' => 'Scuba Diving']);
    ActivityModel::factory()->create(['activity_name' => 'El Nido Tour B (Caves & Coves)']);

    expect($this->router->extractActivityName('scuba diving inclusions'))->toBe('Scuba Diving');
    expect($this->router->extractActivityName('el nido tour b itinerary'))->toBe('El Nido Tour B (Caves & Coves)');
    expect($this->router->extractActivityName('atv and zipline combo, is it offered?'))->toBeNull();
});

test('named tour itinerary routes to activity search not itinerary planner', function () {
    ActivityModel::factory()->create(['activity_name' => 'El Nido Tour B (Caves & Coves)']);

    expect($this->router->classify('el nido tour b itinerary'))->toBe(IntentRouter::ACTIVITY_SEARCH);
    expect($this->router->extractConstraints('el nido tour b itinerary')['activity_name'])
        ->toBe('El Nido Tour B (Caves & Coves)');
});

test('extracts every named activity for comparison', function () {
    ActivityModel::factory()->create(['activity_name' => 'Banana Boat']);
    ActivityModel::factory()->create(['activity_name' => 'Parasailing']);
    ActivityModel::factory()->create(['activity_name' => 'Zipline']);

    expect($this->router->extractActivityNames('compare banana boat and parasailing'))
        ->toBe(['Banana Boat', 'Parasailing']);
    expect($this->router->extractActivityNames('atv and zipline combo'))->toBe(['Zipline']);
    expect($this->router->extractActivityNames('tell me about scuba diving'))->toBe([]);
});

test('resolves concatenated destination names', function () {
    expect($this->router->extractConstraints('elnido tours available tomorrow')['destination_name'])->toBe('El Nido');
    expect($this->router->extractConstraints('how about boracay hotels')['destination_name'])->toBe('Boracay');
});

test('classifies human handoff requests as support agent', function () {
    expect($this->router->classify('i want to talk to a human'))->toBe(IntentRouter::SUPPORT_AGENT);
    expect($this->router->classify('can I speak with an agent please'))->toBe(IntentRouter::SUPPORT_AGENT);
    expect($this->router->classify('connect me to customer service'))->toBe(IntentRouter::SUPPORT_AGENT);
});

test('ordinary travel questions are not mistaken for handoff', function () {
    expect($this->router->classify('hotels in Boracay'))->toBe(IntentRouter::HOTEL_SEARCH);
    expect($this->router->classify('what activities can I talk about?'))->toBe(IntentRouter::ACTIVITY_SEARCH);
});

test('activity location queries include the activity as a place', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Banana Boat',
        'latitude' => 11.9674,
        'longitude' => 121.9248,
    ]);

    $places = $this->router->extractConstraints('where is banana boat located')['place_names'];

    expect($places)->toHaveCount(1);
    expect($places[0]['type'])->toBe('activity');
    expect($places[0]['name'])->toBe('Banana Boat');
});
