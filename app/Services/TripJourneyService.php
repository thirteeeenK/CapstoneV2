<?php

namespace App\Services;

use App\Jobs\SendTripJourneyMail;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Review;
use App\Models\TripPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TripJourneyService
{
    /**
     * Trip reminders are limited per booking trip window, and marketing
     * follow-ups per account, so one trip's notice never suppresses another's
     * and a marketing email never crowds out a time-sensitive reminder.
     */
    private const REMINDER_KINDS = ['pre_trip', 'departure', 'review', 'date_confirmation'];

    private const MARKETING_KINDS = ['continue', 'repeat'];

    /** Kinds that describe one trip window rather than the whole booking. */
    private const WINDOW_KINDS = ['pre_trip', 'departure'];

    /** Service messages a guest booking's contact may receive; reviews and marketing need an account. */
    private const GUEST_KINDS = ['pre_trip', 'departure', 'date_confirmation'];

    private const BOOKING_GROUP = 'booking';

    /** @var array{bookings: int, completed: int, queued: array<string, int>, undated: array<int, string>, inferred_grouping: array<int, string>, no_recipient: array<int, string>} */
    private array $diagnostics = [];

    public function process(): int
    {
        $this->diagnostics = ['bookings' => 0, 'completed' => 0, 'queued' => [], 'undated' => [], 'inferred_grouping' => [], 'no_recipient' => []];
        $count = 0;
        $horizon = now(BookingJourneyContext::TIMEZONE)->subDays(40)->toDateString();
        Booking::query()->whereIn('status', ['approved', 'paid', 'cancellation_denied', 'completed'])
            ->where(fn ($query) => $query->whereHas('items', fn ($items) => $items->where('availability_status', '!=', BookingItem::AVAIL_UNAVAILABLE)
                ->where(fn ($dated) => $dated->where('check_in_date', '>=', $horizon)->orWhere('check_out_date', '>=', $horizon)))
                ->orWhere('trip_end_override', '>=', now()->subDays(40))
                ->orWhere('paid_at', '>=', now()->subHours(72))->orWhere('deposit_secured_at', '>=', now()->subHours(72)))
            ->with(['items', 'user'])->chunkById(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $context = BookingJourneyContext::for($booking);
                    if ($context->tripEnd()?->copy()->addDay()->lte(now()) && $this->completeIfDue($booking)) {
                        $booking->refresh()->load(['items', 'user']);
                        $context = BookingJourneyContext::for($booking);
                        $this->diagnostics['completed']++;
                    }
                    $this->record($context);
                    foreach ($this->candidates($context) as [$kind, $groupKey]) {
                        $queued = $this->enqueueBooking($context, $kind, $groupKey);
                        $this->diagnostics['queued'][$kind] = ($this->diagnostics['queued'][$kind] ?? 0) + $queued;
                        $count += $queued;
                    }
                }
            });
        TripPlan::query()->where('status', '!=', 'draft')->where('updated_at', '<=', now()->subDay())->where('updated_at', '>=', now()->subDays(7))
            ->where('start_date', '>', now('Asia/Manila')->toDateString())->whereDoesntHave('itineraries', fn ($q) => $q->whereNotNull('transferred_at'))
            ->with(['items', 'user'])->chunkById(100, function ($plans) use (&$count) {
                foreach ($plans as $plan) {
                    $user = $plan->user;
                    if ($user && filled($user->email) && $user->trip_marketing_enabled && $plan->items->isNotEmpty()) {
                        $queued = $this->enqueue($user, $user->email, 'continue', 'plan:'.$plan->id.':continue', [
                            'subject' => 'Your '.$plan->title.' is waiting', 'title' => 'Pick up where you left off', 'name' => $user->name,
                            'message' => 'Your saved trip choices are ready to review. Check the current forecast and routes, then generate your itinerary when you’re ready.',
                            'url' => route('trip-planner.index', ['plan' => $plan->id]), 'action' => 'Continue planning', 'schedule' => [], 'marketing' => true,
                        ], null, $plan->id);
                        $this->diagnostics['queued']['continue'] = ($this->diagnostics['queued']['continue'] ?? 0) + $queued;
                        $count += $queued;
                    }
                }
            });

        return $count;
    }

    /**
     * What the last process() run saw, for operator diagnostics.
     *
     * @return array{bookings: int, completed: int, queued: array<string, int>, undated: array<int, string>, inferred_grouping: array<int, string>, no_recipient: array<int, string>}
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * The overall service span of a booking, ignoring which trip window each service is in.
     *
     * @return array{start: Carbon, end: Carbon, signature: string}|null
     */
    public function dates(Booking $booking): ?array
    {
        $context = BookingJourneyContext::for($booking);

        return $context->groups === [] ? null : ['start' => $context->tripStart(), 'end' => $context->tripEnd(), 'signature' => $context->signature()];
    }

    /**
     * Complete a fully paid booking once every included service is recorded
     * fulfilled and its last trip window ended at least 24 hours ago.
     * A booking without any service date is never completed automatically.
     */
    public function completeIfDue(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->lockForUpdate()->with(['items', 'user'])->find($booking->id);
            if (! $locked || ! in_array($locked->status, ['paid', 'cancellation_denied'], true)) {
                return false;
            }
            if ($locked->payment_status !== Booking::PAYMENT_PAID) {
                return false;
            }
            $context = BookingJourneyContext::for($locked);
            $end = $context->tripEnd();
            if (! $end || $end->copy()->addDay()->gt(now()) || $context->included->contains(fn ($item) => $item->fulfillment_status !== 'fulfilled')) {
                return false;
            }

            return $locked->transitionTo(Booking::STATUS_COMPLETED, ['paid', 'cancellation_denied'], [], 'Automatically completed after the trip end and 24-hour grace period; every included service was recorded as fulfilled.');
        });
    }

    /**
     * Whether a journey message of this kind is due for the booking (or one of its trip windows) right now.
     */
    public function bookingEligible(Booking $booking, string $kind, ?string $groupKey = null): bool
    {
        return $this->eligible(BookingJourneyContext::for($booking), $kind, $groupKey ?? self::BOOKING_GROUP);
    }

    private function eligible(BookingJourneyContext $context, string $kind, string $groupKey): bool
    {
        $booking = $context->booking;
        $user = $booking->user;
        if (! $context->recipientEmail || in_array($booking->status, ['pending', 'cancelled', 'rejected', 'expired', 'cancellation_requested'], true)) {
            return false;
        }
        if ($context->isGuest ? ! in_array($kind, self::GUEST_KINDS, true) : ! $this->allowedByPreferences($user, $kind)) {
            return false;
        }
        $now = now();
        if (in_array($kind, self::WINDOW_KINDS, true)) {
            $group = $context->group($groupKey);
            if (! $group || ! $context->reservationSecured()) {
                return false;
            }

            return $kind === 'pre_trip'
                ? $group['start']->gt($now) && $group['start']->lte($now->copy()->addDay())
                : $group['start']->lte($now) && $group['end']->gt($now) && $group['end']->lte($now->copy()->addDay());
        }
        if ($groupKey !== self::BOOKING_GROUP) {
            return false;
        }
        if ($kind === 'date_confirmation') {
            $confirmedAt = $booking->deposit_secured_at ?? $booking->paid_at;

            return $context->reservationSecured() && $context->groups === [] && $context->included->isNotEmpty()
                && $confirmedAt && $confirmedAt->gte($now->copy()->subHours(72));
        }
        $end = $context->tripEnd();
        if (! $end || $booking->status !== Booking::STATUS_COMPLETED) {
            return false;
        }

        return match ($kind) {
            'review' => $end->copy()->addDay()->lte($now) && $end->copy()->addDays(14)->gte($now) && $this->hasUnreviewedService($context, $user),
            'repeat' => $end->copy()->addDays(30)->lte($now) && $end->copy()->addDays(37)->gte($now),
            default => false,
        };
    }

    private function allowedByPreferences(User $user, string $kind): bool
    {
        return in_array($kind, self::MARKETING_KINDS, true) ? (bool) $user->trip_marketing_enabled : (bool) $user->trip_reminders_enabled;
    }

    /**
     * @return array<int, array{0: string, 1: string}> kind and service group of each message due now
     */
    private function candidates(BookingJourneyContext $context): array
    {
        $due = [];
        foreach (array_keys($context->groups) as $groupKey) {
            foreach (self::WINDOW_KINDS as $kind) {
                if ($this->eligible($context, $kind, $groupKey)) {
                    $due[] = [$kind, $groupKey];
                }
            }
        }
        foreach (['date_confirmation', 'review', 'repeat'] as $kind) {
            if ($this->eligible($context, $kind, self::BOOKING_GROUP)) {
                $due[] = [$kind, self::BOOKING_GROUP];
            }
        }

        return $due;
    }

    private function record(BookingJourneyContext $context): void
    {
        $this->diagnostics['bookings']++;
        $code = $context->booking->booking_code;
        if (! $context->recipientEmail) {
            $this->diagnostics['no_recipient'][] = $code;
        }
        if ($context->groups === [] && $context->included->isNotEmpty()) {
            $this->diagnostics['undated'][] = $code;
        }
        if ($context->inferredGrouping) {
            $this->diagnostics['inferred_grouping'][] = $code;
        }
    }

    private function enqueueBooking(BookingJourneyContext $context, string $kind, string $groupKey): int
    {
        $booking = $context->booking;
        $signature = $groupKey === self::BOOKING_GROUP ? $context->signature() : $context->group($groupKey)['signature'];

        return $this->enqueue($booking->user, $context->recipientEmail, $kind, 'booking:'.$booking->id.':'.$groupKey.':'.$kind.':'.$signature,
            $this->bookingPayload($context, $kind, $groupKey), $booking->id, null, $signature, $groupKey);
    }

    private function bookingPayload(BookingJourneyContext $context, string $kind, string $groupKey): array
    {
        $booking = $context->booking;
        $group = $context->group($groupKey);
        $activityOnly = ($group['items'] ?? $context->included)->every(fn ($item) => $item->item_type !== 'room');
        $url = $context->isGuest ? null : route('booking.show', $booking->booking_code);
        [$title, $message, $action] = match ($kind) {
            'pre_trip' => $activityOnly
                ? ['Your booked activities are almost here', 'Review your activity dates and meeting points. Recheck the forecast and ask your operator about any weather restrictions before setting off.', 'Review your trip']
                : ['Your island days are almost here', 'Review your hotel dates, meeting points and planned visits. Recheck the forecast and ask your operator about any weather restrictions before setting off.', 'Review your trip'],
            'departure' => $activityOnly
                ? ['Your trip is wrapping up', 'How is your trip going? Your last booked activity is coming up. Confirm the meeting time with your operator and allow enough time for your onward transport. Contact support if your plans have changed.', 'Check your trip details']
                : ['Before you head home', 'How is your trip going? Check your hotel’s agreed checkout time and allow enough time for your onward transport. Contact support if your stay has been extended.', 'Check departure details'],
            'date_confirmation' => ['Let’s confirm your service dates', 'Your reservation is secured, but its services do not have confirmed dates yet. Reply to our support team with your preferred schedule so we can arrange them. Date-based trip reminders begin once the dates are confirmed.', 'View your booking'],
            'review' => ['Thank you for traveling with SunnyTrips', 'Your completed services are ready for your feedback. Share your experience to help other travelers choose their next stay or activity.', 'Leave a review'],
            'repeat' => ['Ready for another island?', 'Explore our destinations and plan another trip around the places you enjoy. Current availability and weather will guide your next itinerary.', 'Explore destinations'],
        };
        $url = match ($kind) {
            'review' => $url.'#booking-reviews',
            'repeat' => route('trip-planner.index'),
            default => $url,
        };
        $undated = ($group['undated'] ?? collect())->merge($context->undated)->unique('id');

        return ['subject' => $title.' · '.$booking->booking_code, 'title' => $title, 'name' => $context->recipientName,
            'message' => $message, 'url' => $url, 'action' => $action, 'reference' => $booking->booking_code,
            'schedule' => $group ? $this->schedule($context, $group) : [],
            'services' => $group ? $group['items']->map(fn ($item) => ['label' => $this->serviceDate($item), 'name' => $item->item_title, 'detail' => $item->hotel_name])->all() : [],
            'undated' => in_array($kind, [...self::WINDOW_KINDS, 'date_confirmation'], true) ? $undated->map(fn ($item) => $item->item_title)->values()->all() : [],
            'payment_notice' => in_array($kind, self::WINDOW_KINDS, true) ? $this->paymentNotice($context) : null,
            'support' => $context->isGuest ? app(SystemSettingsService::class)->businessContact() : null,
            'guest' => $context->isGuest, 'marketing' => $kind === 'repeat'];
    }

    /**
     * Planner days that fall in this trip window, limited to services still included in the booking.
     *
     * @param  array{start: Carbon, end: Carbon, items: Collection}  $group
     * @return array<int, array{label: string, hotel: ?string, visits: array<int, string>}>
     */
    private function schedule(BookingJourneyContext $context, array $group): array
    {
        $itineraries = $context->booking->trip_itineraries ?? [];
        $includedIds = $context->included->map(fn ($item) => $item->item_snapshot['trip_plan_item_id'] ?? null)->filter()->all();
        $first = $group['start']->toDateString();
        $last = $group['end']->toDateString();

        // A booking can hold several planned trips; each one's days are listed, labeled by trip when there are several.
        return collect($itineraries)->flatMap(fn ($snapshot) => collect($snapshot['days'] ?? [])
            ->filter(fn ($day) => isset($day['date']) && $day['date'] >= $first && $day['date'] <= $last)
            ->map(fn ($day) => [
                'label' => (count($itineraries) > 1 ? ($snapshot['title'] ?? 'Trip').' · ' : '').'Day '.$day['day'].' · '.$day['date'],
                'hotel' => $context->included->contains(fn ($item) => $item->item_type === 'room' && $item->hotel_name === ($day['hotel']['name'] ?? null)) ? $day['hotel']['name'] : null,
                'visits' => collect($day['stops'] ?? [])->filter(fn ($stop) => in_array($stop['plan_item_id'] ?? null, $includedIds, true))
                    ->map(fn ($stop) => $stop['start'].' · '.$stop['name'])->values()->all()]))->values()->all();
    }

    private function serviceDate(BookingItem $item): string
    {
        $start = $item->check_in_date->format('M j, Y');

        return $item->check_out_date && ! $item->check_out_date->isSameDay($item->check_in_date)
            ? $start.' – '.$item->check_out_date->format('M j, Y')
            : $start;
    }

    /**
     * An accurate payment line for a reservation secured by a deposit; a fully paid booking needs none.
     * Balance demands and deadlines stay with the payment notice processor.
     */
    private function paymentNotice(BookingJourneyContext $context): ?string
    {
        $balance = '₱'.number_format($context->remainingBalance(), 2);
        $next = $context->isGuest ? 'Contact our support team to settle it.' : 'Your booking page shows how to settle it.';

        return match ($context->paymentState()) {
            'deposit' => 'Your deposit is received. A balance of '.$balance.' remains. '.$next,
            'overdue' => 'Your remaining balance of '.$balance.' is past due, so this reservation is not fully confirmed. '.$next,
            default => null,
        };
    }

    /**
     * Whether the booking has a fulfilled, reviewable service this traveler has not reviewed yet.
     */
    private function hasUnreviewedService(BookingJourneyContext $context, User $user): bool
    {
        $reviewable = $context->included->filter(fn ($item) => $item->fulfillment_status === 'fulfilled'
            && in_array($item->item_type, ['room', 'activity', 'package'], true));
        if ($reviewable->isEmpty()) {
            return false;
        }
        $reviewed = Review::query()->where('user_id', $user->id)->whereIn('booking_item_id', $reviewable->modelKeys())->pluck('booking_item_id')->all();

        return $reviewable->contains(fn ($item) => ! in_array($item->id, $reviewed));
    }

    private function enqueue(?User $user, string $email, string $kind, string $key, array $payload, ?int $bookingId = null, ?int $planId = null, ?string $signature = null, ?string $group = null): int
    {
        $marketing = in_array($kind, self::MARKETING_KINDS, true);
        $recentSameCategory = function () use ($user, $kind, $marketing, $bookingId, $group): bool {
            $query = DB::table('trip_journey_messages')->where('created_at', '>', now()->subHours(20))->whereIn('status', ['pending', 'sending', 'sent'])
                ->whereIn('kind', $marketing ? self::MARKETING_KINDS : self::REMINDER_KINDS);
            if ($marketing || ! $bookingId) {
                return $query->where('user_id', $user?->id)->exists();
            }

            // Messages queued before trip windows existed have no service group and count for every window.
            return $query->where('booking_id', $bookingId)->where(fn ($q) => $q->where('service_group', $group)->orWhereNull('service_group'))->exists()
                || ($group !== null && DB::table('trip_journey_messages')->where('booking_id', $bookingId)->where('kind', $kind)->whereNull('service_group')
                    ->where('created_at', '>', now()->subDays(3))->whereIn('status', ['pending', 'sending', 'sent'])->exists());
        };
        if ($recentSameCategory()) {
            return 0;
        }

        return DB::transaction(function () use ($user, $email, $kind, $key, $payload, $bookingId, $planId, $signature, $group, $marketing, $recentSameCategory) {
            if ($bookingId && ! $marketing) {
                Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            } else {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            }
            if ($recentSameCategory()) {
                return 0;
            }
            $payload['preferences_url'] = $user ? route('trip-planner.index') : null;
            $inserted = DB::table('trip_journey_messages')->insertOrIgnore(['user_id' => $user?->id, 'recipient_email' => $email, 'booking_id' => $bookingId, 'trip_plan_id' => $planId,
                'service_group' => $group, 'dedupe_key' => $key, 'kind' => $kind, 'date_signature' => $signature, 'payload' => json_encode($payload), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            if ($inserted) {
                SendTripJourneyMail::dispatch(DB::table('trip_journey_messages')->where('dedupe_key', $key)->value('id'))->afterCommit();
            }

            return $inserted;
        });
    }

    public function deliveryEligible(object $message): bool
    {
        return $this->deliveryDecision($message)['eligible'];
    }

    /**
     * Recheck a queued message against the booking or plan as it is now:
     * recipient, status, dates, preferences, included services and fulfillment.
     *
     * @return array{eligible: bool, recipient: ?string, reason: ?string}
     */
    public function deliveryDecision(object $message): array
    {
        $refuse = fn (string $reason) => ['eligible' => false, 'recipient' => null, 'reason' => $reason];
        if (Carbon::parse($message->created_at)->lt(now()->subHours(6))) {
            return $refuse('stale');
        }
        if ($message->booking_id) {
            $booking = Booking::query()->with(['items', 'user'])->find($message->booking_id);
            if (! $booking) {
                return $refuse('booking_missing');
            }
            $context = BookingJourneyContext::for($booking);
            if (! $context->recipientEmail) {
                return $refuse('no_recipient');
            }
            $group = $message->service_group ?? (in_array($message->kind, self::WINDOW_KINDS, true) ? array_key_first($context->groups) : self::BOOKING_GROUP);
            if (! $group || ! $this->eligible($context, $message->kind, $group)) {
                return $refuse('not_eligible');
            }
            $signature = $group === self::BOOKING_GROUP ? $context->signature() : $context->group($group)['signature'];
            if ($message->service_group !== null && $signature !== $message->date_signature) {
                return $refuse('revised');
            }

            return ['eligible' => true, 'recipient' => $context->recipientEmail, 'reason' => null];
        }
        $user = User::query()->find($message->user_id);
        $plan = TripPlan::query()->find($message->trip_plan_id);
        $eligible = $user && filled($user->email) && $user->trip_marketing_enabled && $plan && $plan->start_date !== null && $plan->status !== 'draft'
            && $plan->updated_at->lte(now()->subDay()) && $plan->start_date->gt(now()->startOfDay())
            && ! $plan->itineraries()->whereNotNull('transferred_at')->exists();

        return $eligible ? ['eligible' => true, 'recipient' => $user->email, 'reason' => null] : $refuse('not_eligible');
    }
}
