<?php

use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Services\Chat\ConversationPlanner;
use App\Services\Chat\IntentRouter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.chatbot.planner_enabled' => false]);
    DestinationModel::factory()->create(['name' => 'Boracay']);
    DestinationModel::factory()->create(['name' => 'El Nido']);
    $this->session = ChatSession::factory()->create();
});

test('plans English and Taglish compound asks with shared destination and pax', function (string $message) {
    $plan = app(ConversationPlanner::class)->plan($message, $this->session);
    expect($plan['handle'])->toBeTrue()
        ->and(array_column($plan['tasks'], 'type'))->toBe([IntentRouter::HOTEL_SEARCH, IntentRouter::ACTIVITY_SEARCH])
        ->and(array_column(array_column($plan['tasks'], 'constraints'), 'destination_name'))->toBe(['Boracay', 'Boracay'])
        ->and(array_column(array_column($plan['tasks'], 'constraints'), 'pax'))->toBe([2, 2]);
})->with(['hotel in Boracay for 2 pax and extreme activities', 'hotel sa Boracay at extreme activities, 2 pax']);

test('keeps two tasks of the same type in distinct destinations', function () {
    $tasks = app(ConversationPlanner::class)->plan('hotels in Boracay and hotels in El Nido', $this->session)['tasks'];
    expect(array_column($tasks, 'type'))->toBe([IntentRouter::HOTEL_SEARCH, IntentRouter::HOTEL_SEARCH])
        ->and(array_column(array_column($tasks, 'constraints'), 'destination_name'))->toBe(['Boracay', 'El Nido']);
});

test('does not split amenities into tasks and respects excluded categories', function () {
    $planner = app(ConversationPlanner::class);
    expect($planner->plan('room with pool and Wi-Fi in Boracay', $this->session)['handle'])->toBeFalse();
    $plan = $planner->plan('no hotels, activities lang in Boracay', $this->session);
    expect($plan['handle'])->toBeTrue()->and(array_column($plan['tasks'], 'type'))->toBe([IntentRouter::ACTIVITY_SEARCH]);
});

test('keeps budgets and their bases scoped to each request', function () {
    $tasks = app(ConversationPlanner::class)->plan('hotel under PHP 3000 per night in Boracay and activities under PHP 1000 per person', $this->session)['tasks'];
    expect(array_column(array_column($tasks, 'constraints'), 'max_price'))->toBe([3000, 1000])
        ->and(array_column($tasks, 'budget_basis'))->toBe(['per_night', 'per_person']);
});

test('acknowledges unsupported execution separately from supported searches', function () {
    $tasks = app(ConversationPlanner::class)->plan('book this hotel and find activities in Boracay', $this->session)['tasks'];
    expect(array_column($tasks, 'type'))->toBe([ConversationPlanner::ACTION, IntentRouter::ACTIVITY_SEARCH])
        ->and($tasks[0]['operation'])->toBe('book');
});

test('model proposals cannot inject a query or arbitrary entity identifier', function () {
    config(['services.chatbot.planner_enabled' => true]);
    Http::fake(['*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"tasks":[{"type":"HOTEL_SEARCH","source":"invented hotel id 99"}]}']]]]]])]);
    $tasks = app(ConversationPlanner::class)->plan('hotels in Boracay and suggest scenic viewpoints', $this->session)['tasks'];
    expect($tasks[1]['type'])->toBe(ConversationPlanner::CLARIFY)
        ->and($tasks[1]['query'])->toBe('suggest scenic viewpoints');
});

test('human support requests stay terminal', function () {
    expect(app(ConversationPlanner::class)->plan('talk to an agent about hotels and activities', $this->session)['handle'])->toBeFalse();
});
