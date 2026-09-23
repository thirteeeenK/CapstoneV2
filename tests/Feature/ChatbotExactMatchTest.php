<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Services\Chat\ChatbotService;
use App\Services\Chat\IntentRouter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]]),
    ]);
    $this->boracay = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->cebu = DestinationModel::factory()->create(['name' => 'Cebu']);
    $this->session = ChatSession::create(['session_token' => ChatSession::generateToken()]);
    $this->service = app(ChatbotService::class);
});

test('exact hotel match skips hidden hotels', function () {
    HotelModel::factory()->create([
        'hotel_name' => 'Hidden Gem Resort',
        'destination_id' => $this->boracay->id,
        'is_shown' => false,
    ]);

    $result = $this->service->handle($this->session, null, 'Tell me about Hidden Gem Resort');

    expect(collect($result['retrieved_hotels'] ?? [])->pluck('hotel_name'))
        ->not->toContain('Hidden Gem Resort');
});

test('exact hotel match prefers destination-scoped hit over same-name elsewhere', function () {
    $wrong = HotelModel::factory()->create([
        'hotel_name' => 'Twin Palms',
        'destination_id' => $this->cebu->id,
        'is_shown' => true,
    ]);
    $right = HotelModel::factory()->create([
        'hotel_name' => 'Twin Palms',
        'destination_id' => $this->boracay->id,
        'is_shown' => true,
    ]);

    $result = $this->service->handle($this->session, null, 'Show me Twin Palms in Boracay');

    $hotels = $result['retrieved_hotels'] ?? [];
    expect($hotels)->not->toBeEmpty()
        ->and($hotels[0]['id'])->toBe($right->id)
        ->and($hotels[0]['id'])->not->toBe($wrong->id);
});

test('exact hotel match does not return wrong-destination hotel when destination is constrained', function () {
    HotelModel::factory()->create([
        'hotel_name' => 'Solo Cebu Suites',
        'destination_id' => $this->cebu->id,
        'is_shown' => true,
    ]);

    $result = $this->service->handle($this->session, null, 'Show me Solo Cebu Suites in Boracay');

    expect(collect($result['retrieved_hotels'] ?? [])->pluck('hotel_name'))
        ->not->toContain('Solo Cebu Suites');
});

test('exact activity match skips hidden activities', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Hidden Falls Adventure',
        'destination_id' => $this->boracay->id,
        'is_shown' => false,
    ]);

    $result = $this->service->handle($this->session, null, 'Show me activities called Hidden Falls Adventure');

    expect(collect($result['retrieved_activities'] ?? [])->pluck('activity_name'))
        ->not->toContain('Hidden Falls Adventure');
});

test('exact package match skips inactive packages', function () {
    Package::factory()->create([
        'name' => 'Island Getaway Escape Deal',
        'destination_id' => $this->boracay->id,
        'is_active' => false,
    ]);

    $result = $this->service->handle($this->session, null, 'Show me the package Island Getaway Escape Deal');

    expect(collect($result['retrieved_packages'] ?? [])->pluck('name'))
        ->not->toContain('Island Getaway Escape Deal');
});

test('intent router addon pre-lookup skips hidden addons', function () {
    AddOnModel::factory()->create([
        'name' => 'Private Island Yacht Charter',
        'destination_id' => $this->boracay->id,
        'is_shown' => false,
    ]);
    $visible = AddOnModel::factory()->create([
        'name' => 'Airport Shuttle Transfer',
        'destination_id' => $this->boracay->id,
        'is_shown' => true,
    ]);

    $router = new IntentRouter;

    $hidden = $router->extractConstraints('Do you offer Private Island Yacht Charter?');
    expect($hidden['addon_id'])->toBeNull();

    $shown = $router->extractConstraints('Do you offer Airport Shuttle Transfer?');
    expect($shown['addon_id'])->toBe($visible->id);
});
