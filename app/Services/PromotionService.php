<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Admin-configured Lucky promotions: awarding a code to a generated
 * itinerary, validating it against the exact basket lines it was issued for,
 * and the redemption lifecycle (reserved at submission, redeemed at the first
 * verified payment, released if the unpaid request closes).
 *
 * All money is integer centavos. Discount values come from the terms
 * snapshotted on the offer, never from the browser.
 */
final class PromotionService
{
    /** No promotion may take the payable amount below the ₱1.00 gateway minimum. */
    public const MINIMUM_PAYABLE_CENTS = 100;

    /**
     * Pick an active campaign for a newly generated itinerary, weighted by the
     * admin-defined award weights, or none when no campaign applies.
     *
     * @param  array{destination_id: int, nights: int, pax: int, check_in_date: string}  $selection
     */
    public function awardFor(User $user, array $selection, int $eligibleCents): ?Promotion
    {
        $candidates = Promotion::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('issue_starts_at')->orWhere('issue_starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('issue_ends_at')->orWhere('issue_ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('redeem_ends_at')->orWhere('redeem_ends_at', '>', now()))
            ->where('award_weight', '>', 0)->orderBy('id')->get()
            ->filter(fn (Promotion $promotion) => $this->selectionProblem($promotion->termsSnapshot(), $selection, $eligibleCents) === null
                && $this->hasCapacity($promotion) && $this->redemptionsBy($promotion, $user) < $promotion->per_traveler_limit
                && LuckyOffer::query()->where('promotion_id', $promotion->id)->where('user_id', $user->id)->whereNotNull('code')
                    ->where('created_at', '>=', now()->subDay())->count() < $promotion->codes_per_traveler_per_day)
            ->values();
        if ($candidates->isEmpty()) {
            return null;
        }
        $ticket = random_int(1, $candidates->sum('award_weight'));
        foreach ($candidates as $promotion) {
            $ticket -= $promotion->award_weight;
            if ($ticket <= 0) {
                return $promotion;
            }
        }

        return $candidates->last();
    }

    /**
     * The discount for an eligible subtotal under an offer's terms: percentages
     * round half up to the centavo and respect their cap; nothing exceeds the
     * eligible subtotal or leaves less than the gateway minimum payable.
     *
     * @param  array<string, mixed>  $terms
     */
    public function discountCents(array $terms, int $eligibleCents, int $payableCents): int
    {
        $raw = $terms['discount_type'] === Promotion::TYPE_PERCENT
            ? intdiv($eligibleCents * (int) $terms['percent_off'] + 50, 100)
            : (int) $terms['fixed_off_cents'];
        if (! empty($terms['max_discount_cents'])) {
            $raw = min($raw, (int) $terms['max_discount_cents']);
        }

        return max(0, min($raw, $eligibleCents, $payableCents - self::MINIMUM_PAYABLE_CENTS));
    }

    /**
     * Price a discount code against a subtotal and validate it before any
     * discount is applied: negative or non-numeric prices, invalid, inactive,
     * expired or below-minimum codes and out-of-range discount values are
     * rejected with the original price; a valid code returns the discount and
     * the final price, which never drops below zero.
     *
     * @param  array{code?: string, type?: string, value?: mixed, active?: bool, minPurchase?: mixed, expiresAt?: mixed>  $discountCode
     * @return array{success: bool, originalPrice: float, discountAmount?: float, finalPrice?: float, error?: string}
     */
    public function applyDiscount(mixed $price, array $discountCode): array
    {
        $original = is_numeric($price) ? round((float) $price, 2) : 0.0;
        $fail = fn (string $error) => ['success' => false, 'originalPrice' => $original, 'error' => $error];

        if (! is_numeric($price) || (float) $price < 0) {
            return $fail('Price must be a valid, non-negative number.');
        }

        $code = strtoupper(trim((string) ($discountCode['code'] ?? '')));
        $type = strtolower(trim((string) ($discountCode['type'] ?? '')));
        $value = $discountCode['value'] ?? null;
        $active = (bool) ($discountCode['active'] ?? false);
        $minPurchase = (float) ($discountCode['minPurchase'] ?? 0);
        $expiresAt = $discountCode['expiresAt'] ?? null;

        if ($code === '') {
            return $fail('Discount code is required.');
        }
        if (! in_array($type, ['percentage', 'fixed'], true)) {
            return $fail('Discount code type must be "percentage" or "fixed".');
        }
        if (! is_numeric($value) || (float) $value < 1) {
            return $fail('Discount value must be greater than zero.');
        }
        if ($type === 'percentage' && (float) $value > 100) {
            return $fail('Percentage discount cannot be greater than 100%.');
        }
        if (! $active) {
            return $fail('This discount code is not active.');
        }
        $expired = false;
        if ($expiresAt !== null && $expiresAt !== '') {
            try {
                $expired = Carbon::parse($expiresAt)->isPast();
            } catch (\Throwable) {
                $expired = true;
            }
        }
        if ($expired) {
            return $fail('This discount code has expired.');
        }
        if ((int) round($original * 100) < (int) round($minPurchase * 100)) {
            return $fail('Minimum purchase of ₱'.number_format($minPurchase, 2).' has not been met.');
        }

        $priceCents = (int) round($original * 100);
        $discountCents = $type === 'percentage'
            ? (int) round($priceCents * (float) $value / 100)
            : min((int) round((float) $value * 100), $priceCents);
        $finalCents = max(0, $priceCents - $discountCents);

        return [
            'success' => true,
            'originalPrice' => round($original, 2),
            'discountAmount' => round($discountCents / 100, 2),
            'finalPrice' => round($finalCents / 100, 2),
        ];
    }

    /**
     * Validate a code against the traveler's selected basket lines.
     *
     * @param  Collection<int, CartItem>  $cartItems  the selected basket lines
     * @param  array<int, float>|null  $subtotals  authoritative line subtotals by cart line id (submission); cart prices otherwise
     * @return array{offer: LuckyOffer, promotion: Promotion, terms: array<string, mixed>, lines: Collection<int, CartItem>, eligible_cents: int, discount_cents: int}
     *
     * @throws ValidationException
     */
    public function evaluate(User $user, string $code, Collection $cartItems, int $payableCents, ?array $subtotals = null): array
    {
        $reject = fn (string $message) => throw ValidationException::withMessages(['promotion_code' => $message]);
        $offer = LuckyOffer::query()->with('promotion')->where('code', strtoupper(trim($code)))->first();
        if (! $offer || $offer->user_id !== $user->id || ! $offer->promotion) {
            $reject('This code is not valid for your account.');
        }
        $terms = $offer->terms;
        if (! $offer->promotion->is_active) {
            $reject('This Lucky promotion is no longer active.');
        }
        if ($offer->redemption()->exists()) {
            $reject('This code has already been used and cannot be applied again.');
        }
        if ($offer->isExpired()) {
            $reject('This code expired on '.$offer->expires_at->timezone('Asia/Manila')->format('M j, Y g:i A').'.');
        }
        if (! empty($terms['redeem_starts_at']) && Carbon::parse($terms['redeem_starts_at'])->isFuture()) {
            $reject('This code can be used from '.Carbon::parse($terms['redeem_starts_at'])->timezone('Asia/Manila')->format('M j, Y g:i A').'.');
        }
        if (! empty($terms['redeem_ends_at']) && Carbon::parse($terms['redeem_ends_at'])->isPast()) {
            $reject('This promotion’s redemption period has ended.');
        }

        $lines = $cartItems->where('lucky_offer_id', $offer->id)->values();
        if (($problem = $this->bindingProblem($offer, $lines)) !== null) {
            $reject($problem);
        }
        $eligibleCents = (int) round($lines->sum(fn (CartItem $line) => (float) ($subtotals[$line->id] ?? $line->subtotal)) * 100);
        $selection = ['destination_id' => $offer->destination_id, 'nights' => $offer->nights, 'pax' => $offer->pax, 'check_in_date' => $offer->check_in_date->toDateString()];
        if (($problem = $this->selectionProblem($terms, $selection, $eligibleCents)) !== null) {
            $reject($problem);
        }
        if (! $this->hasCapacity($offer->promotion)) {
            $reject('This Lucky promotion has reached its redemption limit.');
        }
        if ($this->redemptionsBy($offer->promotion, $user) >= (int) ($terms['per_traveler_limit'] ?? 1)) {
            $reject('You have already used this Lucky promotion the maximum number of times.');
        }
        $discount = $this->discountCents($terms, $eligibleCents, $payableCents);
        if ($discount <= 0) {
            $reject('This code gives no discount on the current total.');
        }

        return ['offer' => $offer, 'promotion' => $offer->promotion, 'terms' => $terms, 'lines' => $lines, 'eligible_cents' => $eligibleCents, 'discount_cents' => $discount];
    }

    /**
     * Reserve the redemption for a booking being created, inside its
     * transaction, and allocate the discount across the eligible items.
     *
     * @param  array{offer: LuckyOffer, promotion: Promotion, terms: array<string, mixed>, lines: Collection<int, CartItem>, eligible_cents: int, discount_cents: int}  $evaluation
     * @param  array<int, BookingItem>  $bookingItems  booking items keyed by the cart line they were created from
     */
    public function reserve(Booking $booking, User $user, array $evaluation, array $bookingItems): PromotionRedemption
    {
        $promotion = Promotion::query()->lockForUpdate()->findOrFail($evaluation['promotion']->id);
        LuckyOffer::query()->lockForUpdate()->findOrFail($evaluation['offer']->id);
        if (! $promotion->is_active || ! $this->hasCapacity($promotion) || $this->redemptionsBy($promotion, $user) >= (int) ($evaluation['terms']['per_traveler_limit'] ?? 1)) {
            throw ValidationException::withMessages(['promotion_code' => 'This Lucky promotion is no longer available. Remove the code to continue.']);
        }
        $items = $evaluation['lines']->map(fn (CartItem $line) => $bookingItems[$line->id]);
        $allocations = $this->allocate($items, $evaluation['discount_cents']);
        try {
            $redemption = PromotionRedemption::create([
                'promotion_id' => $promotion->id, 'lucky_offer_id' => $evaluation['offer']->id, 'booking_id' => $booking->id, 'user_id' => $user->id,
                'code' => $evaluation['offer']->code, 'status' => PromotionRedemption::STATUS_RESERVED, 'eligible_cents' => $evaluation['eligible_cents'],
                'discount_cents' => $evaluation['discount_cents'], 'allocations' => $allocations, 'terms' => $evaluation['terms'], 'reserved_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['promotion_code' => 'This code has already been used and cannot be applied again.']);
        }
        $this->applyAllocations($items, $allocations);

        return $redemption;
    }

    /**
     * Mark a booking's reserved redemption used once its first payment is verified.
     */
    public function finalizeForBooking(Booking $booking): void
    {
        PromotionRedemption::query()->where('booking_id', $booking->id)->where('status', PromotionRedemption::STATUS_RESERVED)
            ->update(['status' => PromotionRedemption::STATUS_REDEEMED, 'redeemed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Return the campaign capacity of an unpaid booking that closed. The code is
     * retired, not reissued; a redemption already paid for is never released.
     */
    public function releaseForBooking(Booking $booking, string $reason): void
    {
        PromotionRedemption::query()->where('booking_id', $booking->id)->where('status', PromotionRedemption::STATUS_RESERVED)
            ->update(['status' => PromotionRedemption::STATUS_RELEASED, 'released_at' => now(), 'status_reason' => mb_substr($reason, 0, 160), 'updated_at' => now()]);
    }

    /**
     * Re-evaluate a reserved Lucky discount after the admin's availability
     * review. Excluding or changing any item of the Lucky itinerary breaks the
     * complete-group condition, so the discount is removed and explained.
     *
     * @return array{discount: float, note: ?string}
     */
    public function repriceForApproval(Booking $booking, int $payableCents): array
    {
        $redemption = PromotionRedemption::query()->where('booking_id', $booking->id)->where('status', PromotionRedemption::STATUS_RESERVED)->first();
        if (! $redemption) {
            return ['discount' => (float) $booking->promotion_discount_amount, 'note' => null];
        }
        $items = $booking->items->filter(fn (BookingItem $item) => collect($redemption->allocations)->contains('booking_item_id', $item->id))->values();
        $changed = $items->contains(fn (BookingItem $item) => $item->availability_status === BookingItem::AVAIL_UNAVAILABLE
            || $item->quantity !== (int) (collect($redemption->allocations)->firstWhere('booking_item_id', $item->id)['quantity'] ?? $item->quantity));
        if ($changed) {
            $this->releaseForBooking($booking, 'A Lucky itinerary item was excluded or changed during availability review.');
            $this->applyAllocations($items, collect($items)->map(fn (BookingItem $item) => ['booking_item_id' => $item->id, 'discount_cents' => 0])->all());

            return ['discount' => 0.0, 'note' => 'Lucky code '.$redemption->code.' no longer applies because an item of its itinerary was excluded or changed during availability review; its ₱'
                .number_format($redemption->discount_cents / 100, 2).' discount was removed.'];
        }
        $eligible = (int) round($items->sum(fn (BookingItem $item) => (float) $item->subtotal) * 100);
        $discount = $this->discountCents($redemption->terms, $eligible, $payableCents);
        $previous = $redemption->discount_cents;
        $allocations = $this->allocate($items, $discount);
        $redemption->update(['eligible_cents' => $eligible, 'discount_cents' => $discount, 'allocations' => $allocations]);
        $this->applyAllocations($items, $allocations);

        return ['discount' => $discount / 100, 'note' => $discount !== $previous
            ? 'Lucky code '.$redemption->code.' discount recalculated to ₱'.number_format($discount / 100, 2).' after availability review.' : null];
    }

    /**
     * Split a discount across items in proportion to their subtotals; the
     * largest-remainder method keeps the parts summing to the exact total.
     *
     * @param  Collection<int, BookingItem>  $items
     * @return array<int, array{booking_item_id: int, eligible_cents: int, discount_cents: int, quantity: int}>
     */
    private function allocate(Collection $items, int $discountCents): array
    {
        $eligible = $items->map(fn (BookingItem $item) => (int) round((float) $item->subtotal * 100))->values();
        $total = max(1, $eligible->sum());
        $shares = $eligible->map(fn (int $cents) => intdiv($cents * $discountCents, $total))->all();
        $remainders = $eligible->map(fn (int $cents, int $index) => ($cents * $discountCents) % $total)->sortDesc()->keys();
        foreach ($remainders->take($discountCents - array_sum($shares)) as $index) {
            $shares[$index]++;
        }

        return $items->values()->map(fn (BookingItem $item, int $index) => [
            'booking_item_id' => $item->id, 'eligible_cents' => $eligible[$index], 'discount_cents' => $shares[$index], 'quantity' => $item->quantity,
        ])->all();
    }

    /**
     * @param  Collection<int, BookingItem>  $items
     * @param  array<int, array{booking_item_id: int, discount_cents: int}>  $allocations
     */
    private function applyAllocations(Collection $items, array $allocations): void
    {
        foreach ($items as $item) {
            $share = collect($allocations)->firstWhere('booking_item_id', $item->id)['discount_cents'] ?? 0;
            $item->forceFill(['promotion_discount_amount' => $share / 100])->save();
        }
    }

    /**
     * Why the basket no longer holds the complete, unchanged itinerary the code was issued for, if it does not.
     *
     * @param  Collection<int, CartItem>  $lines
     */
    private function bindingProblem(LuckyOffer $offer, Collection $lines): ?string
    {
        $incomplete = 'The code applies only to its complete, unchanged Lucky itinerary. Keep every item of that itinerary selected, or generate a new Lucky offer.';
        $rooms = $lines->where('item_type', 'room');
        $activities = $lines->where('item_type', 'activity');
        $room = $rooms->first();
        $expectedActivities = collect($offer->activity_ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($rooms->count() !== 1 || $lines->count() !== 1 + count($expectedActivities)
            || $activities->pluck('item_id')->map(fn ($id) => (int) $id)->sort()->values()->all() !== $expectedActivities) {
            return $incomplete;
        }
        if ($room->item_id !== $offer->room_id || $room->quantity !== 1 || $room->check_in_date?->toDateString() !== $offer->check_in_date->toDateString()
            || $room->check_out_date?->toDateString() !== $offer->check_out_date->toDateString()) {
            return 'The room or dates of this Lucky itinerary changed in your basket. Changing a bound room or date needs a new Lucky offer.';
        }
        if ($lines->contains(fn (CartItem $line) => (int) $line->selected_pax !== $offer->pax)) {
            return 'This Lucky offer was issued for '.$offer->pax.' traveler'.($offer->pax === 1 ? '' : 's').'. A different guest count needs a new Lucky offer.';
        }

        return null;
    }

    /**
     * Why an itinerary falls outside a campaign's conditions, if it does.
     *
     * @param  array<string, mixed>  $terms
     * @param  array{destination_id: int, nights: int, pax: int, check_in_date: string}  $selection
     */
    private function selectionProblem(array $terms, array $selection, int $eligibleCents): ?string
    {
        if (! empty($terms['destination_ids']) && ! in_array((int) $selection['destination_id'], array_map('intval', $terms['destination_ids']), true)) {
            return 'This code does not cover the itinerary’s destination.';
        }
        if ((! empty($terms['travel_starts_on']) && $selection['check_in_date'] < $terms['travel_starts_on'])
            || (! empty($terms['travel_ends_on']) && $selection['check_in_date'] > $terms['travel_ends_on'])) {
            return 'This code covers travel between '.($terms['travel_starts_on'] ?? 'now').' and '.($terms['travel_ends_on'] ?? 'any later date').' only.';
        }
        foreach (['nights' => 'night', 'pax' => 'traveler'] as $field => $label) {
            if ((! empty($terms['min_'.$field]) && $selection[$field] < $terms['min_'.$field]) || (! empty($terms['max_'.$field]) && $selection[$field] > $terms['max_'.$field])) {
                return 'This code requires '.($terms['min_'.$field] ?? 1).($terms['max_'.$field] ? '–'.$terms['max_'.$field] : '+').' '.$label.'s.';
            }
        }
        if ($eligibleCents < (int) ($terms['min_eligible_cents'] ?? 0)) {
            return 'This code needs an eligible itinerary subtotal of at least ₱'.number_format($terms['min_eligible_cents'] / 100, 2).'.';
        }

        return null;
    }

    private function hasCapacity(Promotion $promotion): bool
    {
        return $promotion->total_redemption_limit === null
            || PromotionRedemption::query()->where('promotion_id', $promotion->id)->whereIn('status', PromotionRedemption::COUNTED)->count() < $promotion->total_redemption_limit;
    }

    private function redemptionsBy(Promotion $promotion, User $user): int
    {
        return PromotionRedemption::query()->where('promotion_id', $promotion->id)->where('user_id', $user->id)->whereIn('status', PromotionRedemption::COUNTED)->count();
    }
}
