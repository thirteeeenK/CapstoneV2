<?php

use App\Models\ActivityModel;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Services\BookingExpiryService;
use App\Services\BookingQuoteService;
use App\Services\PromotionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->user = onboardedUser();
    $this->trip = luckyTripFixture();
    $this->promotion = Promotion::factory()->create(['percent_off' => 10]);
    $this->actingAs($this->user);
    $offerId = $this->postJson(route('lucky.generate'), ['destination_id' => $this->trip['destination']->id, 'max_budget' => 50000, 'activity_count' => 2, 'nights' => 2, 'pax' => 2])->json('itinerary.offer.id');
    $this->postJson(route('lucky.accept'), ['offer_id' => $offerId])->assertOk();
    $this->offer = LuckyOffer::sole();
    // An ordinary ₱1,000 activity in the same basket keeps its regular price.
    $this->ordinary = CartItem::create(['user_id' => $this->user->id, 'item_type' => 'activity', 'is_selected' => true, 'quantity' => 2, 'selected_pax' => 2,
        'item_id' => ActivityModel::factory()->create(['destination_id' => $this->trip['destination']->id, 'rate' => '₱500/person'])->id]);
    $this->contact = ['contact_name' => 'Ana Reyes', 'contact_email' => $this->user->email, 'contact_phone' => '09171234567', 'guest_manifest' => null];
});

it('shows the won discount at checkout on the Lucky items only, with nothing to type', function () {
    $this->get(route('checkout.index'))->assertOk()
        ->assertViewHas('luckyDiscount', ['offer' => $this->offer->uuid, 'amount' => 400, 'eligible' => '₱4,000.00', 'notice' => null])
        ->assertSee('name="lucky_offer"', false)->assertDontSee('promotion-code-input', false)->assertDontSee('Lucky code');

    expect(PromotionRedemption::count())->toBe(0);
});

it('applies no discount to a basket without a won itinerary', function () {
    $this->offer->update(['promotion_id' => null, 'terms' => null]);

    $this->get(route('checkout.index'))->assertOk()->assertViewHas('luckyDiscount', ['offer' => '', 'amount' => 0, 'eligible' => '', 'notice' => null]);
});

it('uses the largest won discount when the basket holds more than one', function () {
    $bigger = LuckyOffer::factory()->create([
        'user_id' => $this->user->id, 'promotion_id' => $this->promotion->id, 'destination_id' => $this->trip['destination']->id, 'room_id' => $this->trip['room']->id,
        'nights' => 3, 'pax' => 2, 'check_in_date' => now()->addDays(40)->toDateString(), 'check_out_date' => now()->addDays(43)->toDateString(),
        'terms' => ['percent_off' => 50] + $this->promotion->termsSnapshot(),
    ]);
    CartItem::create(['user_id' => $this->user->id, 'item_type' => 'room', 'item_id' => $this->trip['room']->id, 'is_selected' => true, 'quantity' => 1, 'selected_pax' => 2,
        'check_in_date' => $bigger->check_in_date, 'check_out_date' => $bigger->check_out_date, 'lucky_offer_id' => $bigger->id]);

    $this->get(route('checkout.index'))->assertOk()->assertViewHas('luckyDiscount', fn (array $discount) => $discount['offer'] === $bigger->uuid && $discount['amount'] === 1500);
});

it('applies the discount at submission from server prices and ignores forged totals', function () {
    $this->postJson(route('checkout.process'), $this->contact + ['lucky_offer' => $this->offer->uuid, 'promotion_discount_amount' => 4999, 'net_amount' => 1])->assertOk();

    $booking = Booking::sole();
    $redemption = PromotionRedemption::sole();
    $lucky = $booking->items->filter(fn ($item) => ($item->item_snapshot['lucky_offer_id'] ?? null) === $this->offer->id);
    expect((float) $booking->total_amount)->toBe(5000.0)->and((float) $booking->promotion_discount_amount)->toBe(400.0)->and((float) $booking->net_amount)->toBe(4600.0)
        ->and($redemption->status)->toBe('reserved')->and($redemption->booking_id)->toBe($booking->id)
        ->and($lucky)->toHaveCount(3)->and($lucky->sum(fn ($item) => (float) $item->promotion_discount_amount))->toBe(400.0)
        ->and((float) $booking->items->firstWhere('item_id', $this->ordinary->item_id)->promotion_discount_amount)->toBe(0.0);
});

it('rejects a discount won by another traveler', function () {
    $other = onboardedUser();
    CartItem::create(['user_id' => $other->id, 'item_type' => 'activity', 'item_id' => $this->ordinary->item_id, 'is_selected' => true, 'quantity' => 2, 'selected_pax' => 2]);

    $this->actingAs($other)->postJson(route('checkout.process'), $this->contact + ['lucky_offer' => $this->offer->uuid])
        ->assertUnprocessable()->assertJsonValidationErrors(['lucky_offer' => 'not available on your account']);
});

it('explains why an expired, not-yet-valid or paused discount does not apply and keeps the regular price', function (Closure $change, string $message) {
    $change($this->offer, $this->promotion);

    $this->get(route('checkout.index'))->assertOk()
        ->assertViewHas('luckyDiscount', fn (array $discount) => $discount['offer'] === '' && $discount['amount'] === 0 && str_contains($discount['notice'], $message));
})->with([
    'expired' => [fn ($offer) => $offer->update(['expires_at' => now()->subMinute()]), 'expired'],
    'not yet valid' => [fn ($offer) => $offer->update(['terms' => ['redeem_starts_at' => now()->addDay()->toAtomString()] + $offer->terms]), 'can be used from'],
    'paused' => [fn ($offer, $promotion) => $promotion->update(['is_active' => false]), 'no longer active'],
]);

it('requires the complete, unchanged Lucky itinerary', function (Closure $change, string $message) {
    $change($this->offer);

    $this->get(route('checkout.index'))->assertOk()
        ->assertViewHas('luckyDiscount', fn (array $discount) => $discount['amount'] === 0 && str_contains($discount['notice'], $message));
})->with([
    'activity deselected' => [fn ($offer) => CartItem::where('lucky_offer_id', $offer->id)->where('item_type', 'activity')->first()->update(['is_selected' => false]), 'complete, unchanged'],
    'room dates changed' => [fn ($offer) => CartItem::where('lucky_offer_id', $offer->id)->where('item_type', 'room')->first()->update(['check_out_date' => $offer->check_out_date->addDay()]), 'room or dates'],
    'guest count changed' => [fn ($offer) => CartItem::where('lucky_offer_id', $offer->id)->where('item_type', 'room')->first()->update(['selected_pax' => 3]), 'traveler'],
]);

it('books at the regular price when no discount is submitted', function () {
    $this->postJson(route('checkout.process'), $this->contact)->assertOk();

    expect((float) Booking::sole()->net_amount)->toBe(5000.0)->and(PromotionRedemption::count())->toBe(0);
});

it('lets a discount be used once and keeps the last campaign slot for one booking', function () {
    $this->postJson(route('checkout.process'), $this->contact + ['lucky_offer' => $this->offer->uuid])->assertOk();

    expect(fn () => app(PromotionService::class)->evaluate($this->user, $this->offer->uuid, collect(), 100000))->toThrow(ValidationException::class, 'already been used');

    // A second traveler's evaluation passed before the last slot was taken; the locked reservation rechecks it.
    $this->promotion->update(['total_redemption_limit' => 1]);
    $other = onboardedUser();
    $otherOffer = LuckyOffer::factory()->create(['user_id' => $other->id, 'promotion_id' => $this->promotion->id, 'terms' => $this->promotion->termsSnapshot()]);
    $item = BookingItem::factory()->create();
    $evaluation = ['offer' => $otherOffer, 'promotion' => $this->promotion, 'terms' => $otherOffer->terms, 'lines' => collect([new CartItem(['item_type' => 'room'])->forceFill(['id' => 99])]),
        'eligible_cents' => 100000, 'discount_cents' => 10000];
    expect(fn () => app(PromotionService::class)->reserve($item->booking, $other, $evaluation, [99 => $item]))->toThrow(ValidationException::class);
});

it('stops the submission so the traveler can review the total when the discount no longer applies', function () {
    $this->get(route('checkout.index'))->assertViewHas('luckyDiscount', fn (array $discount) => $discount['amount'] === 400);
    CartItem::where('lucky_offer_id', $this->offer->id)->where('item_type', 'activity')->first()->delete();

    $this->postJson(route('checkout.process'), $this->contact + ['lucky_offer' => $this->offer->uuid])
        ->assertUnprocessable()->assertJsonValidationErrors(['lucky_offer' => 'complete, unchanged']);
    expect(Booking::count())->toBe(0)->and(PromotionRedemption::count())->toBe(0);
});

it('rounds percentages half up, honours caps and never goes below the payment minimum', function () {
    $service = app(PromotionService::class);
    $percent = ['discount_type' => 'percent', 'percent_off' => 15, 'max_discount_cents' => null];

    expect($service->discountCents($percent, 333333, 999999))->toBe(50000)
        ->and($service->discountCents($percent, 333303, 999999))->toBe(49995)
        ->and($service->discountCents(['max_discount_cents' => 20000] + $percent, 333333, 999999))->toBe(20000)
        ->and($service->discountCents(['discount_type' => 'fixed', 'fixed_off_cents' => 500000, 'max_discount_cents' => null], 300000, 300000))->toBe(299900)
        ->and(BookingQuoteService::net(5000, 200, 300, 400, 100, 50))->toBe(4650.0);
});

it('redeems at the first verified payment and releases an unpaid request that expires', function () {
    $this->postJson(route('checkout.process'), $this->contact + ['lucky_offer' => $this->offer->uuid]);
    $booking = Booking::sole();
    $booking->update(['status' => 'approved', 'approved_at' => now(), 'payment_deadline' => now()->addDay()]);

    expect($booking->fresh()->markPaid())->toBeTrue()->and(PromotionRedemption::sole()->status)->toBe('redeemed');
    $booking->fresh()->transitionTo('cancelled', ['paid'], [], 'Cancelled after payment.');
    expect(PromotionRedemption::sole()->status)->toBe('redeemed');

    PromotionRedemption::query()->update(['status' => 'reserved', 'redeemed_at' => null]);
    Booking::query()->update(['status' => 'approved', 'payment_deadline' => now()->subMinute()]);
    app(BookingExpiryService::class)->expireIfDue(Booking::sole());
    expect(PromotionRedemption::sole())->status->toBe('released')->status_reason->toContain('expired');
});

it('removes the discount when an admin excludes a Lucky item at approval', function () {
    $this->postJson(route('checkout.process'), $this->contact + ['lucky_offer' => $this->offer->uuid]);
    $booking = Booking::sole()->load('items');
    $excluded = $booking->items->first(fn ($item) => $item->item_type === 'activity' && ($item->item_snapshot['lucky_offer_id'] ?? null) === $this->offer->id);
    $items = $booking->items->mapWithKeys(fn ($item) => [$item->id => ['include' => $item->id === $excluded->id ? 0 : 1, 'quantity' => $item->quantity]])->all();

    $admin = AdminModel::create(['name' => 'Approver', 'email' => 'approver@example.com', 'password' => 'password']);
    $this->actingAs($admin, 'admin')->post(route('admin.bookings.approve', $booking->id), ['items' => $items])->assertRedirect();

    $booking->refresh();
    expect($booking->status)->toBe('approved')->and((float) $booking->promotion_discount_amount)->toBe(0.0)
        ->and((float) $booking->net_amount)->toBe(4000.0)
        ->and(PromotionRedemption::sole()->status)->toBe('released')
        ->and($booking->history()->pluck('note')->join(' '))->toContain('no longer applies');
});

it('bases the installment deposit on the discounted total', function () {
    config(['system_settings.payments.installments_enabled' => true]);
    $this->postJson(route('checkout.process'), $this->contact + ['lucky_offer' => $this->offer->uuid]);
    $booking = Booking::sole()->load('items');
    $items = $booking->items->mapWithKeys(fn ($item) => [$item->id => ['include' => 1, 'quantity' => $item->quantity]])->all();

    $admin = AdminModel::create(['name' => 'Approver', 'email' => 'approver2@example.com', 'password' => 'password']);
    $this->actingAs($admin, 'admin')->post(route('admin.bookings.approve', $booking->id), ['items' => $items]);

    $booking->refresh();
    expect((float) $booking->net_amount)->toBe(4600.0)->and($booking->deposit_required_cents)->toBe(230000)
        ->and($booking->payment_policy['quote_amount'])->toBe(460000);
});
