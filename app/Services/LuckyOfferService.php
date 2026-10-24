<?php

namespace App\Services;

use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\TripPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Durable identity for generated Lucky itineraries. Every itinerary is saved
 * for its owner before it is shown, so acceptance and checkout trust the
 * stored selection and code rather than anything the browser sends back.
 * The dashboard and the chatbot both issue offers through this service.
 */
final class LuckyOfferService
{
    /** Unambiguous characters for printed and pasted codes. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Hours an itinerary without a code can still be accepted at its quoted price. */
    private const UNCODED_VALID_HOURS = 24;

    public function __construct(private readonly PromotionService $promotions, private readonly TripPlannerService $planner) {}

    /**
     * Persist a generated itinerary and award a code when an active campaign applies.
     *
     * @param  array<string, mixed>  $itinerary  the result of LuckyItineraryService::generate()
     */
    public function issue(User $user, array $itinerary, float $maxBudget): LuckyOffer
    {
        $selection = [
            'destination_id' => (int) $itinerary['destination']['id'], 'room_id' => (int) $itinerary['room']['id'],
            'activity_ids' => collect($itinerary['activities'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'nights' => (int) $itinerary['nights'], 'pax' => (int) $itinerary['pax'],
            'check_in_date' => $itinerary['check_in_date'], 'check_out_date' => $itinerary['check_out_date'],
        ];
        $totalCents = (int) round((float) $itinerary['total'] * 100);

        return DB::transaction(function () use ($user, $selection, $totalCents, $maxBudget, $itinerary): LuckyOffer {
            // Serialize a traveler's issuance so the daily code allowance holds under parallel shuffles.
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $promotion = $this->promotions->awardFor($user, $selection, $totalCents);
            $terms = $promotion?->termsSnapshot();
            $expires = $promotion ? now()->addHours($promotion->offer_valid_hours) : now()->addHours(self::UNCODED_VALID_HOURS);
            if ($promotion?->redeem_ends_at && $promotion->redeem_ends_at->lt($expires)) {
                $expires = $promotion->redeem_ends_at->copy();
            }

            return LuckyOffer::create($selection + [
                'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'promotion_id' => $promotion?->id,
                'code' => $promotion ? $this->uniqueCode($promotion->code_prefix) : null,
                'promotion_revision' => $promotion?->revision, 'terms' => $terms,
                'max_budget' => $maxBudget, 'budget_exceeded' => (bool) ($itinerary['budget_exceeded'] ?? false),
                'quoted_total_cents' => $totalCents,
                'estimated_discount_cents' => $terms ? $this->promotions->discountCents($terms, $totalCents, $totalCents) : 0,
                'selection_signature' => hash('sha256', json_encode($selection)),
                'expires_at' => $expires,
            ]);
        });
    }

    /**
     * The offer details shown next to an itinerary.
     *
     * @return array<string, mixed>
     */
    public function present(LuckyOffer $offer): array
    {
        $terms = $offer->terms;

        return [
            'id' => $offer->uuid,
            'code' => $offer->code,
            'expires_at' => $offer->expires_at?->toIso8601String(),
            'formatted_expires_at' => $offer->expires_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
            'discount_label' => $terms ? Promotion::describeDiscount($terms) : null,
            'campaign' => $terms['name'] ?? null,
            'eligible_subtotal' => $offer->quoted_total_cents / 100,
            'formatted_eligible_subtotal' => '₱'.number_format($offer->quoted_total_cents / 100, 2),
            'estimated_saving' => $offer->estimated_discount_cents / 100,
            'formatted_estimated_saving' => '₱'.number_format($offer->estimated_discount_cents / 100, 2),
            'terms' => $terms ? $this->termsSummary($terms) : [],
        ];
    }

    /**
     * Plain-language conditions for an issued code.
     *
     * @param  array<string, mixed>  $terms
     * @return array<int, string>
     */
    public function termsSummary(array $terms): array
    {
        return array_values(array_filter([
            Promotion::describeDiscount($terms).' this Lucky itinerary’s room and activities only; other basket items keep their regular price.',
            'Only for your account, once, and only with every item of this itinerary selected and unchanged (room, dates, activities and guest count).',
            'Apply the code at checkout; it is checked again when you submit the booking request and used when your first payment is verified.',
            ! empty($terms['min_eligible_cents']) ? 'Minimum eligible subtotal: ₱'.number_format($terms['min_eligible_cents'] / 100, 2).'.' : null,
            ! empty($terms['travel_starts_on']) || ! empty($terms['travel_ends_on']) ? 'Travel window: '.($terms['travel_starts_on'] ?? 'now').' to '.($terms['travel_ends_on'] ?? 'open').'.' : null,
            'One Lucky code per booking request; codes cannot be combined. A cancelled, rejected or expired unpaid request retires the code.',
            $terms['terms'] ?? null,
        ]));
    }

    /**
     * A Trip Planner plan holding the offer's room and activities, so the
     * planner can build a checked schedule with routes and weather. One plan
     * per offer; it never touches the basket lines or the code.
     */
    public function planFor(User $user, LuckyOffer $offer): TripPlan
    {
        $existing = TripPlan::query()->where('user_id', $user->id)->where('source_suggestion_uuid', $offer->uuid)->first();
        if ($existing) {
            return $existing;
        }
        $offer->loadMissing('destination');

        return DB::transaction(function () use ($user, $offer): TripPlan {
            $plan = TripPlan::create([
                'user_id' => $user->id, 'destination_id' => $offer->destination_id, 'title' => 'Lucky trip to '.$offer->destination->name,
                'start_date' => $offer->check_in_date->toDateString(), 'end_date' => $offer->check_out_date->toDateString(),
                'pax' => $offer->pax, 'mode' => 'walking', 'revision' => 1, 'source' => 'lucky', 'status' => 'dated', 'duration_days' => $offer->nights + 1,
                'source_suggestion_uuid' => $offer->uuid,
            ]);
            $this->planner->add($plan->refresh(), ['item_type' => 'room', 'item_id' => $offer->room_id, 'quantity' => 1, 'pax' => $offer->pax,
                'check_in' => $offer->check_in_date->toDateString(), 'check_out' => $offer->check_out_date->toDateString()]);
            foreach ($offer->activity_ids as $activityId) {
                $this->planner->add($plan->refresh(), ['item_type' => 'activity', 'item_id' => (int) $activityId, 'pax' => $offer->pax]);
            }

            return $plan->refresh();
        });
    }

    /**
     * The owner's offer by its public id.
     */
    public function owned(User $user, string $uuid): ?LuckyOffer
    {
        return LuckyOffer::query()->where('uuid', $uuid)->where('user_id', $user->id)->first();
    }

    private function uniqueCode(string $prefix): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = strtoupper($prefix).'-'.$suffix;
        } while (LuckyOffer::query()->where('code', $code)->exists());

        return $code;
    }
}
