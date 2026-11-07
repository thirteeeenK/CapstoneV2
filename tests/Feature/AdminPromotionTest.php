<?php

use App\Models\AdminAuditLog;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\SystemSetting;
use App\Services\LuckyOfferService;

beforeEach(function () {
    $this->admin = settingsAdmin();
    $this->valid = [
        'name' => 'Habagat Lucky Week', 'discount_type' => 'percent', 'percent_off' => 12, 'max_discount' => '750.50',
        'min_eligible' => '3000', 'redeem_ends_at' => '2030-12-31T23:59', 'is_active' => '0',
    ];
});

it('creates a paused campaign with peso amounts stored as centavos in Philippine time', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.store'), $this->valid)->assertRedirect(route('admin.promotions.index'));

    $promotion = Promotion::sole();
    expect($promotion)->is_active->toBeFalse()->win_percent->toBe(80)->max_discount_cents->toBe(75050)->min_eligible_cents->toBe(300000)
        ->and($promotion->redeem_ends_at->timezone('Asia/Manila')->format('Y-m-d H:i'))->toBe('2030-12-31 23:59')
        ->and($promotion->created_by_admin_id)->toBe($this->admin->id);
});

it('rejects an incomplete or inconsistent campaign', function (array $override, string $field) {
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.store'), array_merge($this->valid, $override))->assertSessionHasErrors($field);
    expect(Promotion::count())->toBe(0);
})->with([
    'percent above 100' => [['percent_off' => 120], 'percent_off'],
    'fixed without amount' => [['discount_type' => 'fixed', 'percent_off' => null], 'fixed_off'],
    'code expires before it starts' => [['redeem_starts_at' => '2031-01-01T00:00'], 'redeem_ends_at'],
    'zero day duration' => [['duration_days' => 0], 'duration_days'],
    'zero maximum redemptions' => [['total_redemption_limit' => 0], 'total_redemption_limit'],
    'win chance above 100' => [['win_percent' => 101], 'win_percent'],
    'negative minimum spend' => [['min_eligible' => '-1'], 'min_eligible'],
]);

it('raises the revision on a rule change without touching terms already recorded', function () {
    $promotion = Promotion::factory()->create(['percent_off' => 10]);
    $offer = LuckyOffer::factory()->create(['promotion_id' => $promotion->id, 'terms' => $promotion->termsSnapshot()]);

    $this->actingAs($this->admin, 'admin')->put(route('admin.promotions.update', $promotion), array_merge($this->valid, ['percent_off' => 20]))->assertRedirect();

    expect($promotion->fresh())->percent_off->toBe(20)->revision->toBe(2)
        ->and($offer->fresh()->terms['percent_off'])->toBe(10);
});

it('pauses and resumes a campaign', function () {
    $promotion = Promotion::factory()->create();

    $this->actingAs($this->admin, 'admin')->postJson(route('admin.promotions.toggle', $promotion))->assertOk()->assertJson(['is_active' => false]);
    expect($promotion->fresh()->is_active)->toBeFalse();
});

it('serves the live-search results partial for list requests', function () {
    Promotion::factory()->create(['name' => 'Summer Sun']);
    Promotion::factory()->create(['name' => 'Rainy Days']);
    $this->actingAs($this->admin, 'admin');

    $this->get(route('admin.promotions.index'))->assertOk()->assertSee('adminLiveList({', false);
    $this->get(route('admin.promotions.index', ['search' => 'summer']), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('listings-results', false)->assertSee('Summer Sun')->assertDontSee('Rainy Days');
});

it('keeps campaigns behind admin login and two-factor enrollment', function () {
    $this->get(route('admin.promotions.index'))->assertRedirect(route('admin.login'));
    $this->actingAs(onboardedUser())->get(route('admin.promotions.index'))->assertRedirect(route('admin.login'));
    $this->actingAs(settingsAdmin(['two_factor_required' => true]), 'admin')->get(route('admin.promotions.index'))->assertRedirect(route('admin.two-factor.setup'));
});

it('saves a campaign from only its name and discount, filling the win chance and limits', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.store'), [
        'name' => 'Summer Splash 2026', 'discount_type' => 'fixed', 'fixed_off' => '500', 'is_active' => '0',
    ])->assertRedirect(route('admin.promotions.index'));

    expect(Promotion::sole())->win_percent->toBe(80)->fixed_off_cents->toBe(50000)
        ->per_traveler_limit->toBe(1)->offer_valid_hours->toBe(72)->award_weight->toBe(1);
});

it('shows the essential fields with no code to manage', function () {
    $promotion = Promotion::factory()->create(['win_percent' => 35]);
    $this->actingAs($this->admin, 'admin');

    $this->get(route('admin.promotions.create'))->assertOk()
        ->assertSeeInOrder(['Campaign name', 'Win chance', 'Discount', 'Campaign validity', 'Optional limits'])
        ->assertDontSee('Promo code')->assertDontSee('name="award_weight"', false);

    $this->get(route('admin.promotions.edit', $promotion))->assertOk()->assertSee('Maximum redemptions');
    $this->get(route('admin.promotions.index'))->assertOk()->assertSee('35% win chance');
});

it('saves the win chance, including zero', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.store'), array_merge($this->valid, ['win_percent' => 0]))->assertRedirect();

    expect(Promotion::sole()->win_percent)->toBe(0);
});

it('lets admins set the daily Lucky attempts from the promotions page', function () {
    $this->actingAs($this->admin, 'admin');
    $page = $this->get(route('admin.promotions.index'))->assertOk()->assertSee('Daily attempts per traveler')->assertSee('value="5"', false);
    preg_match('/<form action="[^"]*settings\/lucky".*?<\/form>/s', $page->getContent(), $form);
    preg_match_all('/<input type="hidden" name="(section|revision)" value="([^"]*)"/', $form[0], $hiddenFields);

    $this->from(route('admin.promotions.index'))
        ->patch(route('admin.settings.update', 'lucky'), array_combine($hiddenFields[1], $hiddenFields[2]) + ['values' => ['daily_attempts' => 2]])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.promotions.index'));

    expect(SystemSetting::where('section', 'lucky')->sole()->values['daily_attempts'])->toBe(2)
        ->and(app(LuckyOfferService::class)->attempts(onboardedUser()))->toBe(['limit' => 2, 'left' => 2]);
    $this->get(route('admin.settings.index'))->assertOk();
});

it('rejects an invalid daily Lucky attempt limit', function (mixed $attempts) {
    $this->actingAs($this->admin, 'admin')->patch(route('admin.settings.update', 'lucky'), ['section' => 'lucky', 'revision' => 0, 'values' => ['daily_attempts' => $attempts]])
        ->assertSessionHasErrors('values.daily_attempts');
})->with([0, 101, 'many']);

it('keeps rules that are not on the form when a campaign is edited', function () {
    $hiddenRules = ['min_nights' => 2, 'max_pax' => 4, 'per_traveler_limit' => 3, 'offer_valid_hours' => 24, 'award_weight' => 7, 'terms' => 'Weekdays only.'];
    $promotion = Promotion::factory()->create(['percent_off' => 10, 'travel_ends_on' => '2027-03-31', ...$hiddenRules]);

    $this->actingAs($this->admin, 'admin')->put(route('admin.promotions.update', $promotion), [
        'name' => $promotion->name, 'discount_type' => 'percent', 'percent_off' => 10, 'is_active' => '1',
    ])->assertRedirect();

    $saved = $promotion->fresh();
    expect($saved->only(array_keys($hiddenRules)))->toBe($hiddenRules)
        ->and($saved->travel_ends_on->toDateString())->toBe('2027-03-31')
        ->and($saved->revision)->toBe(1);
});

it('saves the maximum redemptions, duration and expiration date', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.store'), array_merge($this->valid, [
        'total_redemption_limit' => 100, 'duration_days' => 30, 'redeem_starts_at' => '2030-06-01T00:00',
    ]))->assertRedirect();

    expect(Promotion::sole())->total_redemption_limit->toBe(100)->duration_days->toBe(30)
        ->and(Promotion::sole()->expiresAt()->timezone('Asia/Manila')->format('Y-m-d'))->toBe('2030-07-01');
});

it('ends a campaign on the earlier of its duration and expiration date', function () {
    $durationFirst = Promotion::factory()->create(['redeem_starts_at' => now()->subDays(10), 'duration_days' => 5, 'redeem_ends_at' => now()->addDay()]);
    $dateFirst = Promotion::factory()->create(['redeem_starts_at' => now()->subDay(), 'duration_days' => 30, 'redeem_ends_at' => now()->addDay()]);
    $fromCreation = Promotion::factory()->create(['duration_days' => 2]);
    $this->travel(3)->days();

    expect($durationFirst->status())->toBe(Promotion::STATUS_EXPIRED)
        ->and($dateFirst->status())->toBe(Promotion::STATUS_EXPIRED)
        ->and($dateFirst->expiresAt()->equalTo($dateFirst->redeem_ends_at))->toBeTrue()
        ->and($fromCreation->status())->toBe(Promotion::STATUS_EXPIRED);
});

it('reports each campaign status', function (array $attributes, string $status, string $label) {
    expect(Promotion::factory()->create($attributes))->status()->toBe($status)->statusLabel()->toBe($label);
})->with([
    'active' => [[], Promotion::STATUS_ACTIVE, 'Active'],
    'scheduled' => [['redeem_starts_at' => now()->addDay()], Promotion::STATUS_SCHEDULED, 'Scheduled'],
    'paused' => [['is_active' => false], Promotion::STATUS_PAUSED, 'Paused'],
    'expired' => [['redeem_ends_at' => now()->subMinute()], Promotion::STATUS_EXPIRED, 'Expired'],
]);

it('shows how many users redeemed, how many slots remain and the status', function () {
    $promotion = Promotion::factory()->create(['name' => 'Summer Sun', 'total_redemption_limit' => 100]);
    PromotionRedemption::factory()->count(2)->create(['promotion_id' => $promotion->id, 'status' => PromotionRedemption::STATUS_REDEEMED]);
    PromotionRedemption::factory()->create(['promotion_id' => $promotion->id, 'status' => PromotionRedemption::STATUS_RESERVED]);
    PromotionRedemption::factory()->create(['promotion_id' => $promotion->id, 'status' => PromotionRedemption::STATUS_RELEASED]);
    $summary = '3 of 100 users redeemed · 97 left · 1 awaiting payment';
    $this->actingAs($this->admin, 'admin');

    $this->get(route('admin.promotions.index'))->assertOk()->assertSee($summary)->assertSee('Active');
    $this->get(route('admin.promotions.edit', $promotion))->assertOk()->assertSee($summary);
});

it('marks a campaign that used its last slot as limit reached, and frees the slot when a booking is released', function () {
    $promotion = Promotion::factory()->create(['total_redemption_limit' => 1]);
    $redemption = PromotionRedemption::factory()->create(['promotion_id' => $promotion->id]);

    expect($promotion->status())->toBe(Promotion::STATUS_LIMIT_REACHED)->and($promotion->remainingRedemptions())->toBe(0);

    $redemption->update(['status' => PromotionRedemption::STATUS_RELEASED]);
    expect($promotion->status())->toBe(Promotion::STATUS_ACTIVE)->and($promotion->remainingRedemptions())->toBe(1);
});

it('lists only travelers who used today’s full Lucky limit', function () {
    config(['system_settings.lucky.daily_attempts' => 2]);
    $exhausted = onboardedUser();
    $under = onboardedUser();
    $yesterday = onboardedUser();
    LuckyOffer::factory()->count(2)->create(['user_id' => $exhausted->id]);
    LuckyOffer::factory()->create(['user_id' => $under->id]);
    LuckyOffer::factory()->count(2)->create(['user_id' => $yesterday->id, 'created_at' => now()->subDays(2)]);

    $this->actingAs($this->admin, 'admin')->get(route('admin.promotions.index'))->assertOk()
        ->assertSee('Out of attempts today')->assertSee($exhausted->email)->assertSee('2 of 2')
        ->assertDontSee($under->email)->assertDontSee($yesterday->email);
});

it('resets one traveler’s attempts, keeps their itineraries and records who did it', function () {
    config(['system_settings.lucky.daily_attempts' => 2]);
    $traveler = onboardedUser();
    $other = onboardedUser();
    LuckyOffer::factory()->count(3)->create(['user_id' => $traveler->id]);
    LuckyOffer::factory()->count(2)->create(['user_id' => $other->id]);
    $this->travel(1)->minute();
    $offers = app(LuckyOfferService::class);
    expect($offers->attempts($traveler)['left'])->toBe(0);

    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.attempts.reset', $traveler))
        ->assertRedirect(route('admin.promotions.index'))->assertSessionHas('success');

    expect($offers->attempts($traveler))->toBe(['limit' => 2, 'left' => 2])
        ->and($offers->attempts($other)['left'])->toBe(0)
        ->and(LuckyOffer::where('user_id', $traveler->id)->count())->toBe(3);
    $this->get(route('admin.promotions.index'))->assertDontSee($traveler->email)->assertSee($other->email);

    $log = AdminAuditLog::where('auditable_id', $traveler->id)->where('auditable_type', $traveler->getMorphClass())->sole();
    expect($log->admin_id)->toBe($this->admin->id)->and($log->new_values['action'])->toBe('lucky_attempts_reset');
});

it('counts attempts made after a reset', function (string $appTimezone) {
    config(['app.timezone' => $appTimezone, 'system_settings.lucky.daily_attempts' => 2]);
    date_default_timezone_set($appTimezone);
    $traveler = onboardedUser();
    LuckyOffer::factory()->count(2)->create(['user_id' => $traveler->id]);
    $this->travel(1)->minute();
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.attempts.reset', $traveler));

    $this->travel(1)->minute();
    LuckyOffer::factory()->create(['user_id' => $traveler->id]);

    expect(app(LuckyOfferService::class)->attempts($traveler)['left'])->toBe(1);
})->with(['UTC', 'Asia/Manila']);

it('keeps attempt resets behind admin login', function () {
    $traveler = onboardedUser();
    LuckyOffer::factory()->count(5)->create(['user_id' => $traveler->id]);

    $this->post(route('admin.promotions.attempts.reset', $traveler))->assertRedirect(route('admin.login'));
    $this->actingAs($traveler)->post(route('admin.promotions.attempts.reset', $traveler))->assertRedirect(route('admin.login'));

    expect(app(LuckyOfferService::class)->attempts($traveler)['left'])->toBe(0);
});
