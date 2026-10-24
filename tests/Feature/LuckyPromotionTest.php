<?php

use App\Models\CartItem;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\TripPlan;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->user = onboardedUser();
    $this->trip = luckyTripFixture();
    $this->filters = ['destination_id' => $this->trip['destination']->id, 'max_budget' => 50000, 'activity_count' => 2, 'nights' => 2, 'pax' => 2];
});

it('saves every generated itinerary and promises no discount without an active campaign', function () {
    Promotion::factory()->inactive()->create();

    $itinerary = $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->assertOk()->json('itinerary');

    $offer = LuckyOffer::sole();
    expect($itinerary['offer']['id'])->toBe($offer->uuid)->and($itinerary['offer']['code'])->toBeNull()
        ->and($offer->user_id)->toBe($this->user->id)->and($offer->quoted_total_cents)->toBe(400000)
        ->and($offer->room_id)->toBe($this->trip['room']->id);
});

it('issues an owner-bound code with its terms, expiry and estimated saving', function () {
    $promotion = Promotion::factory()->create(['name' => 'Rainy Season Lucky', 'percent_off' => 10, 'offer_valid_hours' => 48]);

    $offerData = $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer');

    $offer = LuckyOffer::sole();
    expect($offerData['code'])->toStartWith('LUCKY-')->toBe($offer->code)
        ->and($offerData['formatted_estimated_saving'])->toBe('₱400.00')
        ->and($offerData['terms'])->not->toBeEmpty()
        ->and($offer->terms['percent_off'])->toBe(10)->and($offer->promotion_revision)->toBe($promotion->fresh()->revision)
        ->and($offer->expires_at->diffInHours(now(), true))->toBeGreaterThan(47.9);
});

it('stops awarding codes once the daily reshuffle allowance is used', function () {
    Promotion::factory()->create(['codes_per_traveler_per_day' => 1]);
    $this->actingAs($this->user);

    $first = $this->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.code');
    $second = $this->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.code');

    expect($first)->not->toBeNull()->and($second)->toBeNull();
});

it('never awards a campaign with zero weight or outside its conditions', function () {
    Promotion::factory()->create(['award_weight' => 0]);
    Promotion::factory()->create(['min_pax' => 3]);
    Promotion::factory()->create(['issue_starts_at' => now()->addDay()]);

    expect($this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.code'))->toBeNull();
});

it('accepts a saved offer into bound basket lines exactly once', function () {
    $this->actingAs($this->user);
    $offerId = $this->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.id');

    $this->postJson(route('lucky.accept'), ['offer_id' => $offerId])->assertOk()->assertJson(['success' => true]);
    $this->postJson(route('lucky.accept'), ['offer_id' => $offerId])->assertOk();

    $offer = LuckyOffer::sole();
    expect(CartItem::where('lucky_offer_id', $offer->id)->count())->toBe(3)
        ->and(CartItem::count())->toBe(3)
        ->and($offer->accepted_at)->not->toBeNull()
        ->and(CartItem::pluck('lucky_group_id')->unique()->all())->toBe([$offer->lucky_group_id]);
});

it('refuses another traveler’s offer and an offer whose price changed', function () {
    $offerId = $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.id');

    $this->actingAs(onboardedUser())->postJson(route('lucky.accept'), ['offer_id' => $offerId])->assertNotFound();

    RoomType::whereKey($this->trip['room']->id)->update(['base_price' => 1500]);
    $this->actingAs($this->user)->postJson(route('lucky.accept'), ['offer_id' => $offerId])
        ->assertUnprocessable()->assertJsonFragment(['success' => false]);
    expect(CartItem::count())->toBe(0);
});

it('refuses an expired offer', function () {
    $offerId = $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.id');
    $this->travel(25)->hours();

    $this->postJson(route('lucky.accept'), ['offer_id' => $offerId])->assertUnprocessable();
});

it('opens a saved Lucky itinerary in the Trip Planner once without touching the basket', function () {
    $this->actingAs($this->user);
    $offerId = $this->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.id');

    $first = $this->postJson(route('lucky.plan'), ['offer_id' => $offerId])->assertOk()->json();
    $second = $this->postJson(route('lucky.plan'), ['offer_id' => $offerId])->assertOk()->json();

    $plan = TripPlan::findOrFail($first['plan_id']);
    expect($second['plan_id'])->toBe($plan->id)->and($first['url'])->toBe(route('trip-planner.index', ['plan' => $plan->id]))
        ->and($plan)->source->toBe('lucky')->user_id->toBe($this->user->id)
        ->and($plan->items()->pluck('item_type')->sort()->values()->all())->toBe(['activity', 'activity', 'room'])
        ->and(CartItem::count())->toBe(0);
    $this->actingAs(onboardedUser())->postJson(route('lucky.plan'), ['offer_id' => $offerId])->assertNotFound();
});

it('prunes only expired Lucky offers that nothing uses', function () {
    $this->actingAs($this->user);
    $unused = LuckyOffer::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->trip['destination']->id, 'expires_at' => now()->subDays(40)]);
    $accepted = LuckyOffer::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->trip['destination']->id, 'expires_at' => now()->subDays(40), 'accepted_at' => now()->subDays(41)]);
    $recent = LuckyOffer::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->trip['destination']->id, 'expires_at' => now()->subDays(2)]);
    $planned = LuckyOffer::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->trip['destination']->id, 'expires_at' => now()->subDays(40)]);
    TripPlan::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->trip['destination']->id, 'source' => 'lucky', 'source_suggestion_uuid' => $planned->uuid]);

    $this->artisan('lucky:prune-offers')->assertSuccessful();

    expect(LuckyOffer::pluck('id')->sort()->values()->all())->toBe(collect([$accepted->id, $recent->id, $planned->id])->sort()->values()->all())
        ->and(LuckyOffer::find($unused->id))->toBeNull();
});
