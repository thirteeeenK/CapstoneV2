<?php

use App\Models\LuckyOffer;
use App\Models\Promotion;

beforeEach(function () {
    $this->admin = settingsAdmin();
    $this->valid = [
        'name' => 'Habagat Lucky Week', 'code_prefix' => 'habagat', 'discount_type' => 'percent', 'percent_off' => 12, 'max_discount' => '750.50',
        'min_eligible' => '3000', 'redeem_ends_at' => '2026-12-31T23:59', 'per_traveler_limit' => 1, 'codes_per_traveler_per_day' => 2,
        'offer_valid_hours' => 48, 'award_weight' => 1, 'is_active' => '0',
    ];
});

it('creates a paused campaign with peso amounts stored as centavos in Philippine time', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.store'), $this->valid)->assertRedirect(route('admin.promotions.index'));

    $promotion = Promotion::sole();
    expect($promotion)->is_active->toBeFalse()->code_prefix->toBe('HABAGAT')->max_discount_cents->toBe(75050)->min_eligible_cents->toBe(300000)
        ->and($promotion->redeem_ends_at->timezone('Asia/Manila')->format('Y-m-d H:i'))->toBe('2026-12-31 23:59')
        ->and($promotion->created_by_admin_id)->toBe($this->admin->id);
});

it('rejects an incomplete or inconsistent campaign', function (array $override, string $field) {
    $this->actingAs($this->admin, 'admin')->post(route('admin.promotions.store'), array_merge($this->valid, $override))->assertSessionHasErrors($field);
    expect(Promotion::count())->toBe(0);
})->with([
    'percent above 100' => [['percent_off' => 120], 'percent_off'],
    'fixed without amount' => [['discount_type' => 'fixed', 'percent_off' => null], 'fixed_off'],
    'bad prefix' => [['code_prefix' => 'no spaces!'], 'code_prefix'],
    'max nights below min' => [['min_nights' => 4, 'max_nights' => 2], 'max_nights'],
]);

it('applies edits prospectively by raising the revision without touching issued codes', function () {
    $promotion = Promotion::factory()->create(['percent_off' => 10]);
    $offer = LuckyOffer::factory()->create(['promotion_id' => $promotion->id, 'code' => 'LUCKY-KEEP10', 'terms' => $promotion->termsSnapshot()]);

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
