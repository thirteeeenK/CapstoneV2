<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\DestinationModel;
use App\Models\TripPlan;
use App\Models\User;
use App\Services\TripPlannerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatbotTripPlanService
{
    public const WEATHER_NOTICE = 'Some outdoor and water activities are weather-dependent. Check the forecast and operator updates closer to your travel date.';

    /** Hours after which a suggestion's prices and availability are too old to save. */
    public const SAVE_AFTER_HOURS = 72;

    /** Hours after which the chat shows an estimate as possibly out of date. */
    public const STALE_AFTER_HOURS = 24;

    public function __construct(private TripPlannerService $planner) {}

    /**
     * Rebuild the display stay from saved selections after a draft changes.
     * Existing snapshots retain their original price and availability evidence.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    public function refreshAccommodation(TripPlan $plan, array $snapshot, ?int $preferredRoomId = null): ?array
    {
        $rooms = collect($snapshot['choices'])->where('type', 'room');
        $choice = $rooms->firstWhere('id', $preferredRoomId ?? $snapshot['accommodation']['room_id'] ?? null) ?? $rooms->first();
        if ($choice === null) {
            return null;
        }
        $previous = ($snapshot['accommodation']['room_id'] ?? null) === $choice['id'] ? $snapshot['accommodation'] : [];
        $item = $plan->items()->where('item_type', 'room')->where('item_id', $choice['id'])->first();
        $options = $item?->options ?? [];
        $in = $options['check_in'] ?? $plan->start_date?->toDateString();
        $out = $options['check_out'] ?? $plan->end_date?->toDateString();
        $stay = array_replace($previous, [
            'room_id' => $choice['id'], 'room_name' => $choice['name'],
            'hotel_id' => $previous['hotel_id'] ?? null, 'hotel_name' => $previous['hotel_name'] ?? null,
            'capacity' => $previous['capacity'] ?? null,
            'quantity' => (int) ($options['quantity'] ?? 1), 'pax' => (int) ($options['pax'] ?? $plan->pax),
            'check_in_date' => $in, 'check_out_date' => $out,
            'nights' => $in && $out ? (int) Carbon::parse($in)->diffInDays(Carbon::parse($out)) : max(0, $plan->duration_days - 1),
            'price_basis' => 'per_room_per_night', 'nightly_rate' => null, 'total' => null,
            'estimated_at' => null, 'availability_status' => 'not_checked',
        ]);
        try {
            $room = $this->planner->inventory('room', $choice['id'], $plan->destination_id);
            $stay['room_name'] = $room->room_name;
            $stay['hotel_id'] = $room->hotel_id;
            $stay['hotel_name'] = $room->hotel->hotel_name;
            $stay['capacity'] = (int) $room->max_occupancy;
        } catch (ValidationException) {
            $stay['availability_status'] = 'unavailable';
        }

        return $stay;
    }

    /** @param array<string, mixed> $bundle
     * @param  array<string, mixed>  $constraints
     * @return array<string, mixed>
     */
    public function suggestion(array $bundle, array $constraints): array
    {
        $destination = $bundle['destination'] ?? ['id' => $constraints['destination_id'] ?? null, 'name' => $constraints['destination_name'] ?? ''];
        $count = max(1, min(30, (int) ($bundle['days'] ?? $constraints['days'] ?? (($constraints['nights'] ?? 1) + 1))));
        $start = $bundle['check_in_date'] ?? $constraints['check_in_date'] ?? null;
        $end = $bundle['check_out_date'] ?? $constraints['check_out_date'] ?? null;
        if ($start && $end) {
            $count = (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        }
        $days = array_map(fn (int $day): array => ['day' => $day, 'date' => $start ? Carbon::parse($start)->addDays($day - 1)->toDateString() : null, 'choices' => []], range(1, $count));
        $choices = [];
        if (! empty($bundle['room']['id'])) {
            $choices[] = ['type' => 'room', 'id' => (int) $bundle['room']['id'], 'name' => $bundle['room']['room_name'], 'day' => null];
        }
        foreach ($bundle['activities'] ?? [] as $activity) {
            $day = isset($activity['day']) && $activity['day'] >= 1 && $activity['day'] <= $count ? (int) $activity['day'] : null;
            $choice = ['type' => 'activity', 'id' => (int) $activity['id'], 'name' => $activity['activity_name'], 'day' => $day, 'weather_dependent' => $activity['weather_dependent'] ?? null,
                'detail' => ['category' => $activity['category'] ?? null, 'duration' => $activity['duration'] ?? null, 'image' => $activity['image'] ?? null,
                    'price' => $activity['price_quote']['display'] ?? $activity['rate'] ?? null, 'total' => $activity['price_quote']['total'] ?? null]];
            $choices[] = $choice;
            if ($day !== null) {
                $days[$day - 1]['choices'][] = $choice;
            }
        }

        $accommodation = empty($bundle['room']['id']) ? null : [
            'room_id' => (int) $bundle['room']['id'], 'nightly_rate' => $bundle['room']['nightly_rate'] ?? null, 'total' => $bundle['room']['total'] ?? null,
        ];
        // The same trip content keeps the same identity, so regenerating an
        // unchanged draft does not offer a second save of an already saved plan.
        $identity = hash('sha256', json_encode([$destination['id'], $start, $end, $bundle['pax'] ?? $constraints['pax'] ?? null, $count,
            array_map(fn (array $choice): array => [$choice['type'], $choice['id'], $choice['day']], $choices), $accommodation, $bundle['grand_total'] ?? null]));

        return [
            'version' => 1, 'kind' => 'chatbot_draft', 'id' => self::uuidFromHash($identity),
            'title' => ($destination['name'] ?: 'My').' trip', 'destination_id' => $destination['id'], 'destination' => $destination['name'],
            'start_date' => $start, 'end_date' => $end,
            'pax' => $bundle['pax'] ?? $constraints['pax'] ?? null, 'duration_days' => $count,
            'duration_nights' => max(0, $count - 1),
            'accommodation' => empty($bundle['room']['id']) ? null : [
                'hotel_id' => $bundle['hotel']['id'] ?? null, 'hotel_name' => $bundle['hotel']['name'] ?? null,
                'room_id' => $bundle['room']['id'], 'room_name' => $bundle['room']['room_name'],
                'capacity' => $bundle['room']['max_occupancy'] ?? null, 'quantity' => 1,
                'check_in_date' => $start, 'check_out_date' => $end, 'nights' => max(0, $count - 1),
                'nightly_rate' => $bundle['room']['nightly_rate'] ?? null, 'total' => $bundle['room']['total'] ?? null,
                'price_basis' => 'per_room_per_night', 'estimated_at' => now()->toIso8601String(),
                'availability_status' => $start && $end && array_key_exists('available', $bundle) ? ($bundle['available'] ? 'checked_available' : 'unavailable') : 'not_checked',
            ],
            'accommodation_notice' => $bundle['accommodation_notice'] ?? null,
            'days' => $days, 'choices' => $choices,
            'estimate' => $bundle['grand_total'] ?? null, 'estimate_recorded_at' => now()->toIso8601String(),
            'cost' => $bundle['cost'] ?? null,
            'context' => array_intersect_key($constraints, array_flip(['max_price', 'budget_mode', 'itinerary_budget_basis', 'itinerary_budget_period', 'itinerary_budget_scope', 'preferences', 'excluded_terms', 'excluded_names'])),
            'weather_notice' => self::WEATHER_NOTICE, 'weather_status' => 'not_assessed',
            'notice' => 'Draft only. Availability, opening times, routes and weather have not been checked for this saved schedule.',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private static function uuidFromHash(string $hash): string
    {
        $hex = substr($hash, 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    /**
     * Save a conversation suggestion for an account, or for a guest keyed by
     * their browser session the way the trip basket keys guest cart rows.
     *
     * @return array{plan: TripPlan, created: bool}
     */
    public function save(?User $user, string $sessionToken, string $suggestionId, ?string $title, ?string $chatSessionToken = null): array
    {
        return DB::transaction(function () use ($user, $sessionToken, $suggestionId, $title, $chatSessionToken): array {
            if ($user) {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            }
            $existing = $this->ownedPlans($user, $sessionToken)->where('source_suggestion_uuid', $suggestionId)->first();
            if ($existing) {
                return ['plan' => $existing, 'created' => false];
            }
            $suggestion = $this->currentSuggestion($user, $chatSessionToken, $suggestionId);
            $plan = TripPlan::create([
                'user_id' => $user?->id, 'session_token' => $user ? null : $sessionToken,
                'destination_id' => $suggestion['destination_id'],
                'title' => $title ?? $suggestion['title'], 'source' => 'chatbot', 'status' => 'draft',
                'source_suggestion_uuid' => $suggestionId, 'duration_days' => $suggestion['duration_days'],
                'start_date' => $suggestion['start_date'], 'end_date' => $suggestion['end_date'],
                'pax' => $suggestion['pax'] ?? 2, 'mode' => 'walking', 'revision' => 1,
                'context' => $suggestion['context'] + ['pax_assumed' => $suggestion['pax'] === null],
            ]);
            $suggestion = $this->storeChoices($plan, $suggestion);
            $plan->itineraries()->create(['revision' => 1, 'snapshot' => $suggestion]);

            return ['plan' => $plan, 'created' => true];
        });
    }

    /**
     * Replace a saved chatbot draft with a newer suggestion from the same
     * conversation. Only an owned, still-undated-or-draft chatbot plan can be
     * replaced; its earlier snapshot stays in its version history.
     *
     * @return array{plan: TripPlan, created: bool}
     */
    public function update(?User $user, string $sessionToken, int $planId, string $suggestionId, ?string $chatSessionToken = null): array
    {
        return DB::transaction(function () use ($user, $sessionToken, $planId, $suggestionId, $chatSessionToken): array {
            $plan = $this->ownedPlans($user, $sessionToken)->whereKey($planId)->lockForUpdate()->first();
            if (! $plan || $plan->source !== 'chatbot' || $plan->status !== 'draft') {
                throw ValidationException::withMessages(['plan_id' => 'Only your own saved chatbot draft can be updated. Save this itinerary as a new plan instead.']);
            }
            if ($plan->source_suggestion_uuid === $suggestionId) {
                return ['plan' => $plan, 'created' => false];
            }
            if ($this->ownedPlans($user, $sessionToken)->where('source_suggestion_uuid', $suggestionId)->exists()) {
                throw ValidationException::withMessages(['suggestion_id' => 'This itinerary is already saved as another plan.']);
            }
            $suggestion = $this->currentSuggestion($user, $chatSessionToken, $suggestionId);
            if ((int) $suggestion['destination_id'] !== (int) $plan->destination_id) {
                throw ValidationException::withMessages(['suggestion_id' => 'This itinerary is for another destination. Save it as a new plan instead.']);
            }
            $plan->items()->delete();
            $plan->forceFill([
                'source_suggestion_uuid' => $suggestionId, 'duration_days' => $suggestion['duration_days'],
                'start_date' => $suggestion['start_date'], 'end_date' => $suggestion['end_date'],
                'pax' => $suggestion['pax'] ?? $plan->pax, 'revision' => $plan->revision + 1,
                'context' => $suggestion['context'] + ['pax_assumed' => $suggestion['pax'] === null],
            ])->save();
            $suggestion = $this->storeChoices($plan, $suggestion);
            $plan->itineraries()->create(['revision' => $plan->revision, 'snapshot' => $suggestion]);

            return ['plan' => $plan->refresh(), 'created' => false];
        });
    }

    /**
     * A conversation suggestion that is still current enough to save: estimates
     * and availability evidence older than SAVE_AFTER_HOURS must be regenerated.
     *
     * @return array<string, mixed>
     */
    private function currentSuggestion(?User $user, ?string $chatSessionToken, string $suggestionId): array
    {
        $suggestion = $this->locateSuggestion($user, $chatSessionToken, $suggestionId);
        if (($suggestion['version'] ?? null) !== 1 || ($suggestion['kind'] ?? null) !== 'chatbot_draft') {
            throw ValidationException::withMessages(['suggestion_id' => 'Please generate a current itinerary before saving.']);
        }
        if (! empty($suggestion['generated_at']) && Carbon::parse($suggestion['generated_at'])->lt(now()->subHours(self::SAVE_AFTER_HOURS))) {
            throw ValidationException::withMessages(['suggestion_id' => 'This itinerary\'s prices and availability are more than '.self::SAVE_AFTER_HOURS.' hours old. Update the trip details in the chat for a current version, then save it.']);
        }
        if (! DestinationModel::query()->whereKey($suggestion['destination_id'])->exists()) {
            throw ValidationException::withMessages(['suggestion_id' => 'This destination is no longer available. Please choose another destination.']);
        }

        return $suggestion;
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array<string, mixed> the suggestion with each choice's plan item and availability
     */
    private function storeChoices(TripPlan $plan, array $suggestion): array
    {
        foreach ($suggestion['choices'] as &$choice) {
            try {
                $inventory = $this->planner->inventory($choice['type'], $choice['id'], $plan->destination_id);
                if ($choice['type'] === 'activity') {
                    $environment = $inventory->scheduling['environment'] ?? null;
                    $choice['weather_dependent'] = $environment === null ? null : $environment !== 'indoor';
                }
                $item = $plan->items()->firstOrCreate(['item_type' => $choice['type'], 'item_id' => $choice['id']],
                    ['options' => ['pax' => $plan->pax, 'quantity' => 1, 'day' => $choice['day']]]);
                $choice['plan_item_id'] = $item->id;
                $choice['available'] = true;
            } catch (ValidationException) {
                $choice['available'] = false;
                $choice['notice'] = 'This listing is no longer available. Choose an alternative before scheduling.';
                if ($choice['type'] === 'room' && ! empty($suggestion['accommodation'])) {
                    $suggestion['accommodation']['availability_status'] = 'unavailable';
                }
            }
        }
        unset($choice);

        return $suggestion;
    }

    /**
     * Plans belonging to the account, or to this browser session while the
     * visitor is still a guest.
     *
     * @return Builder<TripPlan>
     */
    public function ownedPlans(?User $user, string $sessionToken): Builder
    {
        return $user
            ? TripPlan::query()->where('user_id', $user->id)
            : TripPlan::query()->whereNull('user_id')->where('session_token', $sessionToken);
    }

    /**
     * Move this session's guest plans onto the account, mirroring the trip
     * basket's claim-on-read. A suggestion the account already saved wins, so
     * the duplicate guest row is discarded instead of breaking its unique key.
     */
    public function claimGuestPlans(User $user, string $sessionToken): int
    {
        if ($sessionToken === '') {
            return 0;
        }

        return DB::transaction(function () use ($user, $sessionToken): int {
            $plans = TripPlan::query()->whereNull('user_id')->where('session_token', $sessionToken)->lockForUpdate()->get();
            $claimed = 0;
            foreach ($plans as $plan) {
                $duplicate = $plan->source_suggestion_uuid !== null && TripPlan::query()
                    ->where('user_id', $user->id)->where('source_suggestion_uuid', $plan->source_suggestion_uuid)->exists();
                if ($duplicate) {
                    $plan->delete();

                    continue;
                }
                $plan->forceFill(['user_id' => $user->id, 'session_token' => null])->save();
                $claimed++;
            }

            return $claimed;
        });
    }

    /**
     * The authoritative suggestion stored in chat history. A guest may only
     * reach one through an unclaimed session whose token they presented, which
     * is the isolation rule ConversationManager already applies to the chat.
     *
     * @return array<string, mixed>
     */
    private function locateSuggestion(?User $user, ?string $chatSessionToken, string $suggestionId): array
    {
        $message = ChatMessage::query()->where('sender', 'bot')
            ->whereHas('chatSession', function ($query) use ($user, $chatSessionToken): void {
                if ($user) {
                    $query->where('user_id', $user->id);

                    return;
                }
                $query->whereNull('user_id')->where('session_token', (string) $chatSessionToken);
            })
            ->where('context_data->itinerary_suggestions->'.$suggestionId.'->id', $suggestionId)->firstOrFail();

        return $message->context_data['itinerary_suggestions'][$suggestionId];
    }
}
