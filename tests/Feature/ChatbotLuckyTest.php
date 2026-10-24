<?php

use App\Models\CartItem;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Services\Chat\IntentRouter;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
    $this->trip = luckyTripFixture();
    // Lucky replies are built by the server; the model must not be asked.
    $gemini = Mockery::mock(GeminiService::class)->makePartial();
    $gemini->shouldReceive('generateEmbedding')->andReturn(null);
    $gemini->shouldNotReceive('generateChatResponse');
    app()->instance(GeminiService::class, $gemini);
});

it('recognises explicit Lucky requests but not ordinary surprises', function (string $message, bool $expected) {
    expect(app(IntentRouter::class)->hasLuckyIntent($message))->toBe($expected);
})->with([
    ["I'm feeling lucky", true],
    ['Surprise me with a trip to Siargao', true],
    ['give me a random itinerary under 15000', true],
    ['how do I surprise my wife with a spa day', false],
    ['lucky beach hotels in Boracay', false],
]);

it('guides a guest to sign in before any offer is issued', function () {
    $this->postJson('/chat', ['message' => 'Surprise me with a Lucky trip'])
        ->assertOk()->assertJsonPath('lucky.requires_login', true)->assertJsonPath('lucky.login_url', route('login'));

    expect(LuckyOffer::count())->toBe(0);
});

it('issues the same persisted offer and code as the Lucky page from budget, nights and travelers in chat', function () {
    Promotion::factory()->create(['percent_off' => 10]);
    $user = onboardedUser();

    $response = $this->actingAs($user)->postJson('/chat', ['message' => "I'm feeling lucky: 2 nights in Siargao for 2 people under 10000"])->assertOk();

    $offer = LuckyOffer::sole();
    $response->assertJsonPath('lucky.offer.id', $offer->uuid)->assertJsonPath('lucky.offer.code', $offer->code)
        ->assertJsonPath('lucky.itinerary.total', 4000)->assertJsonPath('lucky.offer.formatted_estimated_saving', '₱400.00');
    expect($offer)->user_id->toBe($user->id)->nights->toBe(2)->pax->toBe(2)->destination_id->toBe($this->trip['destination']->id)
        ->and($response->json('reply'))->toContain($offer->code)->toContain('₱4,000.00');

    $this->postJson($response->json('lucky.accept_url'), ['offer_id' => $offer->uuid])->assertOk();
    expect(CartItem::where('lucky_offer_id', $offer->id)->count())->toBe(3);
});

it('says when no promotion applies instead of inventing a code', function () {
    $response = $this->actingAs(onboardedUser())->postJson('/chat', ['message' => 'surprise me'])->assertOk();

    expect($response->json('lucky.offer.code'))->toBeNull()
        ->and($response->json('reply'))->toContain('No Lucky promotion applies');
});
