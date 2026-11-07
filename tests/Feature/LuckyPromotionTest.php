<?php

use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\RoomType;
use App\Models\TripPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/** Tests run on UTC while the deployed app runs on Philippine time; timing rules must hold on both. */
function runAppIn(string $timezone): void
{
    config(['app.timezone' => $timezone]);
    date_default_timezone_set($timezone);
}

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
    expect($itinerary['offer']['id'])->toBe($offer->uuid)->and($itinerary['offer']['won'])->toBeFalse()
        ->and($offer->user_id)->toBe($this->user->id)->and($offer->quoted_total_cents)->toBe(400000)
        ->and($offer->room_id)->toBe($this->trip['room']->id);
});

it('awards a discount with its terms, expiry and estimated saving', function () {
    $promotion = Promotion::factory()->create(['name' => 'Rainy Season Lucky', 'percent_off' => 10, 'offer_valid_hours' => 48]);

    $offerData = $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer');

    $offer = LuckyOffer::sole();
    expect($offerData)->not->toHaveKey('code')->and($offerData['won'])->toBeTrue()->and($offer->promotion_id)->toBe($promotion->id)
        ->and($offerData['formatted_estimated_saving'])->toBe('₱400.00')
        ->and($offerData['terms'])->not->toBeEmpty()
        ->and($offer->terms['percent_off'])->toBe(10)->and($offer->promotion_revision)->toBe($promotion->fresh()->revision)
        ->and($offer->expires_at->diffInHours(now(), true))->toBeGreaterThan(47.9);
});

it('still gives an itinerary at the regular price when an attempt does not win', function () {
    Promotion::factory()->create(['win_percent' => 0]);

    $response = $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->assertOk();

    expect($response->json('itinerary.total'))->toEqual(4000)->and($response->json('itinerary.offer.won'))->toBeFalse()
        ->and(LuckyOffer::sole())->promotion_id->toBeNull()->estimated_discount_cents->toBe(0);
});

it('never awards a campaign with zero weight or outside its conditions', function () {
    Promotion::factory()->create(['award_weight' => 0]);
    Promotion::factory()->create(['min_pax' => 3]);
    Promotion::factory()->create(['redeem_starts_at' => now()->addDay()]);
    Promotion::factory()->create(['redeem_ends_at' => now()->subDay()]);

    expect($this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.won'))->toBeFalse();
});

it('stops awarding a campaign once its redemptions are used up, or to a traveler who already used it', function () {
    $full = Promotion::factory()->create(['total_redemption_limit' => 1]);
    PromotionRedemption::factory()->create(['promotion_id' => $full->id]);
    $used = Promotion::factory()->create();
    PromotionRedemption::factory()->create(['promotion_id' => $used->id, 'user_id' => $this->user->id]);

    expect($this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->json('itinerary.offer.won'))->toBeFalse();
});

it('ends a won discount with its campaign', function () {
    $promotion = Promotion::factory()->create(['redeem_ends_at' => now()->addHours(5)]);

    $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->assertOk();

    expect(LuckyOffer::sole()->expires_at->equalTo($promotion->redeem_ends_at))->toBeTrue();
});

it('counts attempts down and refuses more once the daily limit is used', function () {
    config(['system_settings.lucky.daily_attempts' => 2]);
    $this->actingAs($this->user);
    $this->get(route('lucky.index'))->assertOk()->assertViewHas('attempts', ['limit' => 2, 'left' => 2]);

    $this->postJson(route('lucky.generate'), $this->filters)->assertOk()->assertJsonPath('attempts.left', 1);
    $this->postJson(route('lucky.generate'), $this->filters)->assertOk()->assertJsonPath('attempts.left', 0);
    $this->postJson(route('lucky.generate'), $this->filters)->assertTooManyRequests()
        ->assertJsonPath('success', false)->assertJsonPath('attempts.left', 0);

    expect(LuckyOffer::count())->toBe(2);
    $this->get(route('lucky.index'))->assertViewHas('attempts', ['limit' => 2, 'left' => 0]);
});

it('keeps each traveler’s attempts separate', function () {
    config(['system_settings.lucky.daily_attempts' => 1]);
    $this->actingAs($this->user)->postJson(route('lucky.generate'), $this->filters)->assertOk();

    $this->actingAs(onboardedUser())->postJson(route('lucky.generate'), $this->filters)->assertOk();
});

it('does not spend an attempt when no itinerary could be built', function () {
    config(['system_settings.lucky.daily_attempts' => 1]);
    $this->actingAs($this->user);

    $this->postJson(route('lucky.generate'), array_merge($this->filters, ['destination_id' => DestinationModel::factory()->create()->id]))->assertUnprocessable();

    $this->postJson(route('lucky.generate'), $this->filters)->assertOk();
});

it('resets attempts at midnight Philippine time', function (string $appTimezone) {
    runAppIn($appTimezone);
    config(['system_settings.lucky.daily_attempts' => 1]);
    $this->actingAs($this->user);
    $this->travelTo(Carbon::parse('2027-02-01 23:50', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertOk();

    $this->travelTo(Carbon::parse('2027-02-01 23:59', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertTooManyRequests();

    $this->travelTo(Carbon::parse('2027-02-02 00:01', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertOk();
})->with(['UTC', 'Asia/Manila']);

it('counts an early morning attempt toward that Philippine day', function (string $appTimezone) {
    runAppIn($appTimezone);
    config(['system_settings.lucky.daily_attempts' => 1]);
    $this->actingAs($this->user);
    $this->travelTo(Carbon::parse('2027-02-02 01:00', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertOk();

    $this->travelTo(Carbon::parse('2027-02-02 07:00', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertTooManyRequests();

    $this->travelTo(Carbon::parse('2027-02-02 23:00', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertTooManyRequests();
})->with(['UTC', 'Asia/Manila']);

it('does not count yesterday evening toward today', function (string $appTimezone) {
    runAppIn($appTimezone);
    config(['system_settings.lucky.daily_attempts' => 1]);
    $this->actingAs($this->user);
    $this->travelTo(Carbon::parse('2027-02-01 18:00', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertOk();

    $this->travelTo(Carbon::parse('2027-02-02 09:00', 'Asia/Manila'));
    $this->postJson(route('lucky.generate'), $this->filters)->assertOk();
})->with(['UTC', 'Asia/Manila']);

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
