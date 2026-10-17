<?php

namespace App\Services;

use App\Jobs\SendTripJourneyMail;
use App\Models\Booking;
use App\Models\TripPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TripJourneyService
{
    public function process(): int
    {
        $count = 0;
        Booking::query()->whereNotNull('trip_itineraries')->whereIn('status', ['paid', 'cancellation_denied', 'completed'])
            ->with(['items', 'user'])->chunkById(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $dates = $this->dates($booking);
                    if ($dates && $dates['end']->copy()->addDay()->lte(now()) && $this->completeIfDue($booking)) {
                        $booking->refresh()->load(['items', 'user']);
                    }
                    foreach (['pre_trip', 'departure', 'review', 'repeat'] as $kind) {
                        if ($this->bookingEligible($booking, $kind)) {
                            $dates = $this->dates($booking);
                            $count += $this->enqueue($booking->user, $kind, 'booking:'.$booking->id.':'.$kind.':'.$dates['signature'], $this->bookingPayload($booking, $kind), $booking->id, null, $dates['signature']);
                        }
                    }
                }
            });
        TripPlan::query()->where('updated_at', '<=', now()->subDay())->where('updated_at', '>=', now()->subDays(7))
            ->where('start_date', '>', now('Asia/Manila')->toDateString())->whereDoesntHave('itineraries', fn ($q) => $q->whereNotNull('transferred_at'))
            ->with(['items', 'user'])->chunkById(100, function ($plans) use (&$count) {
                foreach ($plans as $plan) {
                    $user = $plan->user;
                    if ($user && $user->trip_marketing_enabled && $user->email_verified_at && $plan->items->isNotEmpty()) {
                        $count += $this->enqueue($user, 'continue', 'plan:'.$plan->id.':continue', [
                            'subject' => 'Your '.$plan->title.' is waiting', 'title' => 'Pick up where you left off', 'name' => $user->name,
                            'message' => 'Your saved trip choices are ready to review. Check the current forecast and routes, then generate your itinerary when you’re ready.',
                            'url' => route('trip-planner.index'), 'action' => 'Continue planning', 'schedule' => [], 'marketing' => true,
                        ], null, $plan->id);
                    }
                }
            });

        return $count;
    }

    public function dates(Booking $booking): ?array
    {
        $items = $booking->items->reject(fn ($item) => $item->availability_status === 'unavailable');
        if ($items->isEmpty() || $items->contains(fn ($item) => ! $item->check_in_date || ($item->item_type === 'room' && ! $item->check_out_date))) {
            return null;
        }
        $start = $items->map(fn ($item) => Carbon::parse($item->check_in_date->toDateString().' 09:00', 'Asia/Manila'))->sortBy('timestamp')->first();
        $end = $items->map(fn ($item) => Carbon::parse(($item->check_out_date ?? $item->check_in_date)->toDateString().' 23:59:59', 'Asia/Manila'))
            ->sortByDesc('timestamp')->first();
        if ($booking->trip_end_override && $booking->trip_end_override->gt($end)) {
            $end = $booking->trip_end_override->copy();
        }

        return ['start' => $start, 'end' => $end, 'signature' => hash('sha256', json_encode([$items->map(fn ($item) => [$item->id, $item->check_in_date?->toDateString(), $item->check_out_date?->toDateString(), $item->quantity])->all(), $end->toIso8601String()]))];
    }

    public function completeIfDue(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->lockForUpdate()->with('items')->find($booking->id);
            if (! $locked || ! $locked->trip_itineraries || ! in_array($locked->status, ['paid', 'cancellation_denied'], true)) {
                return false;
            }
            if ($locked->payment_plan_version === 2 && $locked->payment_status !== Booking::PAYMENT_PAID) {
                return false;
            }
            $dates = $this->dates($locked);
            $included = $locked->items->reject(fn ($item) => $item->availability_status === 'unavailable');
            if (! $dates || $dates['end']->copy()->addDay()->gt(now()) || $included->contains(fn ($item) => $item->fulfillment_status !== 'fulfilled')) {
                return false;
            }

            return $locked->transitionTo(Booking::STATUS_COMPLETED, ['paid', 'cancellation_denied'], [], 'Automatically completed after the trip end and 24-hour grace period; every included service was recorded as fulfilled.');
        });
    }

    public function bookingEligible(Booking $booking, string $kind): bool
    {
        $user = $booking->user;
        if (! $user || ! $user->email_verified_at || ! $booking->trip_itineraries || in_array($booking->status, ['cancelled', 'rejected', 'expired', 'cancellation_requested'], true)) {
            return false;
        }
        $dates = $this->dates($booking);
        if (! $dates) {
            return false;
        }
        $now = now();
        $confirmed = in_array($booking->status, ['paid', 'cancellation_denied'], true);

        return match ($kind) {
            'pre_trip' => $user->trip_reminders_enabled && $confirmed && $dates['start']->gt($now) && $dates['start']->lte($now->copy()->addDay()),
            'departure' => $user->trip_reminders_enabled && $confirmed && $dates['start']->lte($now) && $dates['end']->gt($now) && $dates['end']->lte($now->copy()->addDay()),
            'review' => $user->trip_reminders_enabled && $booking->status === 'completed' && $dates['end']->copy()->addDay()->lte($now)
                && $dates['end']->copy()->addDays(14)->gte($now) && $booking->items->contains(fn ($item) => $item->availability_status !== 'unavailable' && $item->fulfillment_status === 'fulfilled' && in_array($item->item_type, ['room', 'activity', 'package'], true)),
            'repeat' => $user->trip_marketing_enabled && $booking->status === 'completed' && $dates['end']->copy()->addDays(30)->lte($now) && $dates['end']->copy()->addDays(37)->gte($now),
            default => false,
        };
    }

    private function bookingPayload(Booking $booking, string $kind): array
    {
        [$title, $message, $action, $url] = match ($kind) {
            'pre_trip' => ['Your island days are almost here', 'Review your hotel dates, meeting points and planned visits. Recheck the forecast and ask your operator about any weather restrictions before setting off.', 'Review your trip', route('booking.show', $booking->booking_code)],
            'departure' => ['Before you head home', 'How is your trip going? Check your hotel’s agreed checkout time and allow enough time for your onward transport. Contact support if your stay has been extended.', 'Check departure details', route('booking.show', $booking->booking_code)],
            'review' => ['Thank you for traveling with SunnyTrips', 'Your completed services are ready for your feedback. Share your experience to help other travelers choose their next stay or activity.', 'Leave a review', route('booking.show', $booking->booking_code).'#booking-reviews'],
            'repeat' => ['Ready for another island?', 'Explore our destinations and plan another trip around the places you enjoy. Current availability and weather will guide your next itinerary.', 'Explore destinations', route('trip-planner.index')],
        };
        $snapshot = $booking->trip_itineraries[0];
        $included = $booking->items->reject(fn ($item) => $item->availability_status === 'unavailable');
        $includedIds = $included->map(fn ($item) => $item->item_snapshot['trip_plan_item_id'] ?? null)->filter()->all();

        return ['subject' => $title.' · '.$booking->booking_code, 'title' => $title, 'name' => $booking->user->name,
            'message' => $message, 'url' => $url, 'action' => $action, 'reference' => $booking->booking_code,
            'schedule' => in_array($kind, ['pre_trip', 'departure'], true) ? collect($snapshot['days'])->map(fn ($day) => ['label' => 'Day '.$day['day'].' · '.$day['date'],
                'hotel' => $included->contains(fn ($item) => $item->item_type === 'room' && $item->hotel_name === ($day['hotel']['name'] ?? null)) ? $day['hotel']['name'] : null,
                'visits' => collect($day['stops'])->filter(fn ($stop) => in_array($stop['plan_item_id'], $includedIds, true))
                    ->map(fn ($stop) => $stop['start'].' · '.$stop['name'])->all()])->all() : [],
            'marketing' => $kind === 'repeat'];
    }

    private function enqueue(User $user, string $kind, string $key, array $payload, ?int $bookingId = null, ?int $planId = null, ?string $signature = null): int
    {
        if (DB::table('trip_journey_messages')->where('user_id', $user->id)->where('created_at', '>', now()->subHours(20))->whereIn('status', ['pending', 'sending', 'sent'])->exists()) {
            return 0;
        }

        return DB::transaction(function () use ($user, $kind, $key, $payload, $bookingId, $planId, $signature) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (DB::table('trip_journey_messages')->where('user_id', $user->id)->where('created_at', '>', now()->subHours(20))->whereIn('status', ['pending', 'sending', 'sent'])->exists()) {
                return 0;
            }
            $payload['preferences_url'] = route('trip-planner.index');
            $inserted = DB::table('trip_journey_messages')->insertOrIgnore(['user_id' => $user->id, 'booking_id' => $bookingId, 'trip_plan_id' => $planId,
                'dedupe_key' => $key, 'kind' => $kind, 'date_signature' => $signature, 'payload' => json_encode($payload), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            if ($inserted) {
                SendTripJourneyMail::dispatch(DB::table('trip_journey_messages')->where('dedupe_key', $key)->value('id'))->afterCommit();
            }

            return $inserted;
        });
    }

    public function deliveryEligible(object $message): bool
    {
        $user = User::query()->find($message->user_id);
        if (! $user || ! $user->email_verified_at || Carbon::parse($message->created_at)->lt(now()->subHours(6))) {
            return false;
        }
        if ($message->booking_id) {
            $booking = Booking::query()->with(['items', 'user'])->find($message->booking_id);

            return $booking && $this->bookingEligible($booking, $message->kind) && ($this->dates($booking)['signature'] ?? null) === $message->date_signature;
        }
        $plan = TripPlan::query()->find($message->trip_plan_id);

        return $user->trip_marketing_enabled && $plan && $plan->updated_at->lte(now()->subDay()) && $plan->start_date->gt(now()->startOfDay())
            && ! $plan->itineraries()->whereNotNull('transferred_at')->exists();
    }
}
