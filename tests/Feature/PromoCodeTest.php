<?php

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Services\PromotionService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->user = onboardedUser();
    $this->destination = DestinationModel::factory()->create();
    // ₱500/person × 2 travelers = ₱1,000 basket.
    $this->line = CartItem::create(['user_id' => $this->user->id, 'item_type' => 'activity', 'is_selected' => true, 'quantity' => 2, 'selected_pax' => 2,
        'item_id' => ActivityModel::factory()->create(['destination_id' => $this->destination->id, 'rate' => '₱500/person'])->id]);
    $this->promotion = Promotion::factory()->create(['name' => 'Summer Splash', 'code' => 'SUMMER10', 'percent_off' => 10]);
    $this->contact = ['contact_name' => 'Ana Reyes', 'contact_email' => $this->user->email, 'contact_phone' => '09171234567', 'guest_manifest' => null];
    $this->actingAs($this->user);
});

it('shows a promo code box at checkout and previews a typed code', function () {
    $this->get(route('checkout.index'))->assertOk()->assertSee('id="promo-code-input"', false)->assertSee('name="promo_code"', false);

    $this->postJson(route('checkout.promo-code'), ['promo_code' => ' summer10 '])->assertOk()
        ->assertJson(['success' => true, 'code' => 'SUMMER10', 'discount' => 100, 'eligible' => '₱1,000.00', 'label' => '10% off']);

    expect(PromotionRedemption::count())->toBe(0);
});

it('applies the code at submission from server prices and reserves one use', function () {
    $this->postJson(route('checkout.process'), $this->contact + ['promo_code' => 'SUMMER10', 'promotion_discount_amount' => 999])->assertOk();

    $booking = Booking::sole();
    $redemption = PromotionRedemption::sole();
    expect((float) $booking->promotion_discount_amount)->toBe(100.0)->and((float) $booking->net_amount)->toBe(900.0)
        ->and($redemption)->status->toBe(PromotionRedemption::STATUS_RESERVED)->lucky_offer_id->toBeNull()->promotion_id->toBe($this->promotion->id)
        ->and($booking->promotionDiscountLabel())->toBe('Promo code SUMMER10');

    $this->get(route('booking.show', $booking->booking_code))->assertOk()->assertSee('Promo code SUMMER10');
});

it('rejects codes that cannot be used', function (Closure $setup, string $message) {
    $setup($this);

    $this->postJson(route('checkout.promo-code'), ['promo_code' => 'SUMMER10'])->assertUnprocessable()
        ->assertJsonValidationErrors(['promo_code' => $message]);
})->with([
    'paused' => [fn ($test) => $test->promotion->update(['is_active' => false]), 'not active'],
    'expired' => [fn ($test) => $test->promotion->update(['redeem_ends_at' => now()->subDay()]), 'expired'],
    'below minimum spend' => [fn ($test) => $test->promotion->update(['min_eligible_cents' => 500000]), 'at least ₱5,000.00'],
    'other destination' => [fn ($test) => $test->promotion->update(['destination_ids' => [DestinationModel::factory()->create()->id]]), 'does not cover'],
]);

it('rejects an unknown code and a forged code at submission', function () {
    $this->postJson(route('checkout.promo-code'), ['promo_code' => 'NOPE'])->assertUnprocessable()->assertJsonValidationErrors(['promo_code' => 'not valid']);
    $this->postJson(route('checkout.process'), $this->contact + ['promo_code' => 'NOPE'])->assertUnprocessable()->assertJsonValidationErrors('promo_code');

    expect(Booking::count())->toBe(0);
});

it('limits each traveler to the campaign per-traveler uses', function () {
    $this->postJson(route('checkout.process'), $this->contact + ['promo_code' => 'SUMMER10'])->assertOk();
    $this->line->replicate()->fill(['is_selected' => true])->save();

    $this->postJson(route('checkout.promo-code'), ['promo_code' => 'SUMMER10'])->assertUnprocessable()
        ->assertJsonValidationErrors(['promo_code' => 'maximum number of times']);
});

it('never awards a promo code campaign as a Lucky prize', function () {
    $selection = ['destination_id' => $this->destination->id, 'nights' => 2, 'pax' => 2, 'check_in_date' => now()->addDays(10)->toDateString()];

    expect(app(PromotionService::class)->awardFor($this->user, $selection, 100000))->toBeNull();
});

it('lets an admin create a promotion with a unique uppercase code', function () {
    $admin = settingsAdmin();
    $valid = ['name' => 'Habagat Week', 'code' => 'habagat-15', 'discount_type' => 'percent', 'percent_off' => 15, 'is_active' => '1'];

    $this->actingAs($admin, 'admin')->post(route('admin.promotions.store'), $valid)->assertRedirect(route('admin.promotions.index'));
    expect(Promotion::where('name', 'Habagat Week')->value('code'))->toBe('HABAGAT-15');

    $this->post(route('admin.promotions.store'), array_merge($valid, ['name' => 'Copy']))->assertSessionHasErrors(['code' => 'Another promotion already uses this promo code.']);
    $this->post(route('admin.promotions.store'), array_merge($valid, ['code' => 'bad code!']))->assertSessionHasErrors('code');
    $this->get(route('admin.promotions.index'))->assertOk()->assertSee('HABAGAT-15');
});

it('reprices a code discount over the items still included after admin review', function () {
    $this->line->replicate()->fill(['is_selected' => true])->save();
    $this->postJson(route('checkout.process'), $this->contact + ['promo_code' => 'SUMMER10'])->assertOk();
    $booking = Booking::sole()->load('items');
    expect((float) $booking->promotion_discount_amount)->toBe(200.0);

    $booking->items->first()->update(['availability_status' => BookingItem::AVAIL_UNAVAILABLE]);
    $result = app(PromotionService::class)->repriceForApproval($booking->fresh('items'), 100000);

    expect($result['discount'])->toEqual(100)->and($result['note'])->toContain('recalculated')
        ->and(PromotionRedemption::sole())->status->toBe(PromotionRedemption::STATUS_RESERVED)->discount_cents->toBe(10000);
});
