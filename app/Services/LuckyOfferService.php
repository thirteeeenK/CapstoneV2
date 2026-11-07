<?php

namespace App\Services;

use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\TripPlan;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Durable identity for generated Lucky itineraries. Every itinerary is saved
 * for its owner before it is shown, so acceptance and checkout trust the
 * stored selection and any won discount rather than anything the browser sends back.
 * The dashboard and the chatbot both issue offers through this service.
 */
final class LuckyOfferService
{
    public const ATTEMPTS_USED_MESSAGE = 'You have used all your Lucky attempts for today. They reset at midnight Philippine time.';

    /** Hours an itinerary without a discount can still be accepted at its quoted price. */
    private const NO_DISCOUNT_VALID_HOURS = 24;

    public function __construct(private readonly PromotionService $promotions, private readonly TripPlannerService $planner, private readonly SystemSettingsService $settings) {}

    /**
     * A traveler's Lucky attempts today. Every saved itinerary is one attempt;
     * the day runs from midnight Philippine time, or from an admin's reset.
     *
     * @return array{limit: int, left: int}
     */
    public function attempts(User $user): array
    {
        $limit = (int) $this->settings->section('lucky')['daily_attempts'];
        $used = LuckyOffer::query()->where('user_id', $user->id)->where('created_at', '>=', $this->startOfLuckyDay())
            ->whereRaw("created_at > COALESCE((SELECT lucky_attempts_reset_at FROM users WHERE users.id = lucky_offers.user_id), '-infinity')")->count();

        return ['limit' => $limit, 'left' => max(0, $limit - $used)];
    }

    /**
     * Travelers who have used today's full limit, most recent first.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function exhaustedTravelers(): LengthAwarePaginator
    {
        $limit = (int) $this->settings->section('lucky')['daily_attempts'];

        return User::query()->join('lucky_offers', 'lucky_offers.user_id', '=', 'users.id')
            ->where('lucky_offers.created_at', '>=', $this->startOfLuckyDay())
            ->whereRaw('(users.lucky_attempts_reset_at IS NULL OR lucky_offers.created_at > users.lucky_attempts_reset_at)')
            ->select('users.id', 'users.name', 'users.email')->selectRaw('COUNT(*) AS attempts_used, MAX(lucky_offers.created_at) AS last_attempt_at')
            ->groupBy('users.id', 'users.name', 'users.email')->havingRaw('COUNT(*) >= ?', [$limit])
            ->orderByDesc('last_attempt_at')->paginate(10, ['*'], 'exhausted_page');
    }

    /**
     * Give a traveler the full daily limit back.
     */
    public function resetAttempts(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['lucky_attempts_reset_at' => now()->format('Y-m-d H:i:s')]);
    }

    /**
     * Midnight in the Philippines on the app's clock, which is how
     * lucky_offers.created_at is stored: it has no time zone, so an offset would be ignored.
     */
    private function startOfLuckyDay(): string
    {
        return now('Asia/Manila')->startOfDay()->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * Persist a generated itinerary as one Lucky attempt and roll for a
     * discount, or return null when the traveler has no attempts left.
     *
     * @param  array<string, mixed>  $itinerary  the result of LuckyItineraryService::generate()
     */
    public function issue(User $user, array $itinerary, float $maxBudget): ?LuckyOffer
    {
        $selection = [
            'destination_id' => (int) $itinerary['destination']['id'], 'room_id' => (int) $itinerary['room']['id'],
            'activity_ids' => collect($itinerary['activities'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'nights' => (int) $itinerary['nights'], 'pax' => (int) $itinerary['pax'],
            'check_in_date' => $itinerary['check_in_date'], 'check_out_date' => $itinerary['check_out_date'],
        ];
        $totalCents = (int) round((float) $itinerary['total'] * 100);

        return DB::transaction(function () use ($user, $selection, $totalCents, $maxBudget, $itinerary): ?LuckyOffer {
            // Serialize a traveler's attempts so the daily limit holds under parallel shuffles.
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($this->attempts($user)['left'] <= 0) {
                return null;
            }
            $promotion = $this->promotions->awardFor($user, $selection, $totalCents);
            $terms = $promotion?->termsSnapshot();
            $expires = $promotion ? now()->addHours($promotion->offer_valid_hours) : now()->addHours(self::NO_DISCOUNT_VALID_HOURS);
            $campaignEnds = $promotion?->expiresAt();
            if ($campaignEnds?->lt($expires)) {
                $expires = $campaignEnds->copy();
            }

            return LuckyOffer::create($selection + [
                'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'promotion_id' => $promotion?->id,
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
            'won' => $terms !== null,
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
     * Plain-language conditions for a won discount.
     *
     * @param  array<string, mixed>  $terms
     * @return array<int, string>
     */
    public function termsSummary(array $terms): array
    {
        return array_values(array_filter([
            Promotion::describeDiscount($terms).' this Lucky itinerary’s room and activities only; other basket items keep their regular price.',
            'Only for your account, once, and only with every item of this itinerary selected and unchanged (room, dates, activities and guest count).',
            'It is applied automatically at checkout, checked again when you submit the booking request, and used when your first payment is verified.',
            ! empty($terms['min_eligible_cents']) ? 'Minimum eligible subtotal: ₱'.number_format($terms['min_eligible_cents'] / 100, 2).'.' : null,
            ! empty($terms['travel_starts_on']) || ! empty($terms['travel_ends_on']) ? 'Travel window: '.($terms['travel_starts_on'] ?? 'now').' to '.($terms['travel_ends_on'] ?? 'open').'.' : null,
            'One Lucky discount per booking request; discounts cannot be combined. A cancelled, rejected or expired unpaid request retires the discount.',
            $terms['terms'] ?? null,
        ]));
    }

    /**
     * A Trip Planner plan holding the offer's room and activities, so the
     * planner can build a checked schedule with routes and weather. One plan
     * per offer; it never touches the basket lines or the discount.
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
}
