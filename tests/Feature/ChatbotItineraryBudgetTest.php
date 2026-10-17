<?php

use App\Http\Middleware\EnforceGuestChatLimits;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\Chat\IntentRouter;
use Illuminate\Support\Facades\Http;

/**
 * Catalog with predictable group prices (2 pax, 2 nights):
 * stays ₱4,000 / ₱8,000 / ₱16,000; activities ₱500–₱4,000 flat.
 */
function budgetCatalogHotel(int $destinationId, string $name, int $nightly): void
{
    $hotel = HotelModel::factory()->create([
        'hotel_name' => $name,
        'destination_id' => $destinationId,
        'is_shown' => true,
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => $name.' Room',
        'base_price' => $nightly,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'is_shown' => true,
    ]);
}

function budgetCatalogActivity(int $destinationId, string $name, int $flatRate, string $category): void
{
    ActivityModel::factory()->create([
        'activity_name' => $name,
        'destination_id' => $destinationId,
        'rate' => '₱'.number_format($flatRate).' flat',
        'category' => $category,
        'is_shown' => true,
    ]);
}

beforeEach(function () {
    $this->withoutMiddleware(EnforceGuestChatLimits::class);

    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Here is your itinerary!']]]]],
        ]),
    ]);

    $boracay = DestinationModel::factory()->create(['name' => 'Boracay']);
    budgetCatalogHotel($boracay->id, 'Budget Inn', 2000);
    budgetCatalogHotel($boracay->id, 'Midrange Hotel', 4000);
    budgetCatalogHotel($boracay->id, 'Luxe Resort', 8000);
    budgetCatalogActivity($boracay->id, 'Kayak', 500, 'Water Activity');
    budgetCatalogActivity($boracay->id, 'Island Hopping Tour', 1500, 'Island Hopping');
    budgetCatalogActivity($boracay->id, 'ATV Ride', 2500, 'Adventure');
    budgetCatalogActivity($boracay->id, 'Sunset Cruise', 3000, 'Water Activity');
    budgetCatalogActivity($boracay->id, 'Helmet Diving', 4000, 'Water Activity');
});

function planItinerary(string $message): array
{
    return test()->postJson('/chat', ['message' => $message])->assertOk()->json();
}

test('a target budget is spent within ±5% with a full day plan', function () {
    $response = planItinerary('Plan a 3-day itinerary in Boracay for 2 pax with a ₱13,000 budget');

    expect($response['itinerary']['grand_total'])->toEqual(13000)
        ->and($response['itinerary']['hotel']['name'])->toBe('Midrange Hotel')
        ->and($response['itinerary']['activities'])->toHaveCount(3)
        ->and($response['itinerary']['budget_check']['status'])->toBe('within')
        ->and($response['itinerary']['budget_check']['mode'])->toBe('target')
        ->and($response['reply'])->toContain('**Budget check:** this plan comes to **₱13,000.00** — 100% of your ₱13,000.00 budget.');
});

test('staying within budget beats a fuller plan that runs over, and the shortfall is explained', function () {
    $response = planItinerary('Plan a 3-day itinerary in Boracay for 2 pax with a ₱20,000 budget');

    expect($response['itinerary']['grand_total'])->toEqual(20000)
        ->and($response['itinerary']['hotel']['name'])->toBe('Luxe Resort')
        ->and(collect($response['itinerary']['activities'])->pluck('activity_name')->all())->toBe(['Island Hopping Tour', 'ATV Ride'])
        ->and($response['itinerary']['budget_check']['status'])->toBe('within')
        ->and($response['reply'])->toContain('To stay within budget it includes 2 activities instead of 3.');
});

test('a budget the catalog cannot reach gets the highest-value plan, an explanation, and a breakdown', function () {
    $response = planItinerary('Plan a 3-day itinerary in Boracay for 2 pax with a ₱60,000 budget');

    expect($response['itinerary']['grand_total'])->toEqual(25500)
        ->and($response['itinerary']['hotel']['name'])->toBe('Luxe Resort')
        ->and($response['itinerary']['budget_check']['status'])->toBe('under_target')
        ->and($response['reply'])->toContain('₱34,500.00 under your ₱60,000.00 budget')
        ->and($response['reply'])->toContain("It's already the highest-value plan we can build")
        ->and($response['reply'])->toContain('**Cost breakdown** (2 guests, 2 nights)')
        ->and($response['reply'])->toContain('- Stay: Luxe Resort Room at Luxe Resort — ₱8,000.00 × 2 nights = ₱16,000.00');
});

test('an unreachable low budget shows the cheapest plan and explains why it is over', function () {
    $response = planItinerary('Plan a 3-day itinerary in Boracay for 2 pax with a ₱3,000 budget');

    expect($response['itinerary']['grand_total'])->toEqual(4000)
        ->and($response['itinerary']['hotel']['name'])->toBe('Budget Inn')
        ->and($response['itinerary']['within_budget'])->toBeFalse()
        ->and($response['itinerary']['over_budget'])->toBeTrue()
        ->and($response['reply'])->toContain('over budget')
        ->and($response['reply'])->toContain('The cheapest available stay alone costs ₱4,000.00 for 2 nights.')
        ->and($response['reply'])->toContain('Activities start at ₱500.00 for your group.')
        ->and($response['reply'])->toContain('₱1,000.00 over');
});

test('a requested activity is always part of the budget-fitted plan', function () {
    $response = planItinerary('Plan a 3-day itinerary in Boracay for 2 pax with Helmet Diving and a ₱13,000 budget');

    expect($response['itinerary']['grand_total'])->toEqual(12500)
        ->and($response['itinerary']['activities'][0]['activity_name'])->toBe('Helmet Diving')
        ->and($response['itinerary']['budget_check']['status'])->toBe('within');
});

test('without a budget the plan is value-first and asks for one instead of assuming ₱20,000', function () {
    $response = planItinerary('Plan a 3-day itinerary in Boracay for 2 pax');

    expect($response['itinerary']['budget'])->toBeNull()
        ->and($response['itinerary']['budget_check']['status'])->toBe('no_budget')
        ->and($response['itinerary']['grand_total'])->toEqual(8500)
        ->and($response['itinerary']['hotel']['name'])->toBe('Budget Inn')
        ->and($response['reply'])->toContain('**No budget given**')
        ->and($response['reply'])->toContain("I'll fit the plan to it");
});

test('a target may land slightly over budget but a ceiling never does', function () {
    $coron = DestinationModel::factory()->create(['name' => 'Coron']);
    budgetCatalogHotel($coron->id, 'Coron Lodge', 5000);
    budgetCatalogActivity($coron->id, 'Reef Snorkel', 1000, 'Water Activity');
    budgetCatalogActivity($coron->id, 'Lake Swim', 1000, 'Sightseeing');

    $target = planItinerary('Plan a 3-day itinerary in Coron for 2 pax with a ₱11,800 budget');
    expect($target['itinerary']['grand_total'])->toEqual(12000)
        ->and($target['itinerary']['budget_check']['status'])->toBe('slightly_over')
        ->and($target['reply'])->toContain('slightly over budget')
        ->and($target['reply'])->toContain('₱200.00 (1.7%) over your ₱11,800.00 budget');

    $ceiling = planItinerary('Plan a 3-day itinerary in Coron for 2 pax under ₱11,800');
    expect($ceiling['itinerary']['grand_total'])->toEqual(11000)
        ->and($ceiling['itinerary']['budget_check']['mode'])->toBe('ceiling')
        ->and($ceiling['itinerary']['budget_check']['status'])->toBe('under_target')
        ->and($ceiling['reply'])->toContain('the next option up costs ₱12,000.00');
});

test('budget wording is parsed into an amount and a ceiling or target reading', function (string $message, ?int $amount, ?string $mode) {
    $constraints = app(IntentRouter::class)->extractConstraints($message);

    expect($constraints['max_price'])->toBe($amount)
        ->and($constraints['budget_mode'])->toBe($mode);
})->with([
    'stated budget is a target' => ['trip with a ₱20,000 budget', 20000, 'target'],
    'budget of is a target' => ['budget of 20k', 20000, 'target'],
    'colon budget' => ['itinerary for 2 pax, budget: 20000', 20000, 'target'],
    'under is a ceiling' => ['trip under 20k', 20000, 'ceiling'],
    'P-prefixed pesos' => ['under P20,000 trip', 20000, 'ceiling'],
    'max amount is a ceiling' => ['max 15000 for 2 nights', 15000, 'ceiling'],
    'Taglish only' => ['20k lang', 20000, 'ceiling'],
    'a guest count is not a price' => ['room for max 4 guests', null, null],
]);
