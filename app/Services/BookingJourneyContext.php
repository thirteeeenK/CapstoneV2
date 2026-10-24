<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\Payment\BookingPaymentAccountingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A source-independent view of one booking for trip journey messages.
 *
 * Catalog checkout, Lucky, chatbot, Trip Planner, mixed baskets and admin
 * bookings all reduce to the same shape: a recipient, the included services,
 * and the trip windows (service groups) formed by the dated services.
 * A saved itinerary only enriches a window; it is never required.
 */
final class BookingJourneyContext
{
    public const TIMEZONE = 'Asia/Manila';

    /**
     * @param  Collection<int, BookingItem>  $included
     * @param  array<string, array{key: string, start: Carbon, end: Carbon, items: Collection<int, BookingItem>, undated: Collection<int, BookingItem>, sources: array<int, string>, signature: string}>  $groups
     * @param  Collection<int, BookingItem>  $undated  included services with no confirmed date that belong to no dated window
     */
    private function __construct(
        public readonly Booking $booking,
        public readonly ?string $recipientEmail,
        public readonly ?string $recipientName,
        public readonly bool $isGuest,
        public readonly Collection $included,
        public readonly array $groups,
        public readonly Collection $undated,
        public readonly bool $inferredGrouping,
    ) {}

    public static function for(Booking $booking): self
    {
        $booking->loadMissing(['items', 'user']);
        $user = $booking->user;
        $included = $booking->items->reject(fn (BookingItem $item) => $item->availability_status === BookingItem::AVAIL_UNAVAILABLE)->values();
        [$groups, $undated, $inferred] = self::groups($booking, $included);

        // Only an admin-created booking without an account falls back to its saved contact email.
        $isGuest = $user === null;
        $guestContact = $isGuest && ($booking->isAgentBooked() || str_starts_with((string) $booking->booking_source, 'admin_'));
        $email = $user ? $user->email : ($guestContact ? $booking->contact_email : null);

        return new self($booking, filled($email) ? $email : null, $user?->name ?? $booking->contact_name, $isGuest, $included, $groups, $undated, $inferred);
    }

    /**
     * The window a message refers to, or null once that window no longer exists.
     *
     * @return array{key: string, start: Carbon, end: Carbon, items: Collection<int, BookingItem>, undated: Collection<int, BookingItem>, sources: array<int, string>, signature: string}|null
     */
    public function group(string $key): ?array
    {
        return $this->groups[$key] ?? null;
    }

    public function tripStart(): ?Carbon
    {
        return collect($this->groups)->min(fn (array $group) => $group['start']);
    }

    public function tripEnd(): ?Carbon
    {
        return collect($this->groups)->max(fn (array $group) => $group['end']);
    }

    /**
     * A revision of the whole booking's services, for booking-level messages.
     */
    public function signature(): string
    {
        return hash('sha256', json_encode([
            $this->included->map(fn (BookingItem $item) => [$item->id, $item->check_in_date?->toDateString(), $item->check_out_date?->toDateString(), $item->quantity])->all(),
            $this->tripEnd()?->toIso8601String(),
        ]));
    }

    /**
     * Whether payment secures the reservation: full payment, or a verified
     * installment deposit while the booking is still approved.
     */
    public function reservationSecured(): bool
    {
        if ($this->booking->payment_status === Booking::PAYMENT_PAID) {
            return in_array($this->booking->status, [Booking::STATUS_PAID, Booking::STATUS_CANCELLATION_DENIED], true);
        }

        return $this->booking->payment_plan_version === 2 && $this->booking->deposit_secured_at !== null
            && in_array($this->booking->status, [Booking::STATUS_APPROVED, Booking::STATUS_CANCELLATION_DENIED], true);
    }

    /**
     * @return 'paid'|'deposit'|'overdue'|'unconfirmed'
     */
    public function paymentState(): string
    {
        if ($this->booking->payment_status === Booking::PAYMENT_PAID) {
            return 'paid';
        }
        if ($this->booking->payment_plan_version === 2 && $this->booking->deposit_secured_at) {
            return $this->booking->balance_overdue_at ? 'overdue' : 'deposit';
        }

        return 'unconfirmed';
    }

    public function remainingBalance(): float
    {
        if ($this->booking->payment_status === Booking::PAYMENT_PAID) {
            return 0.0;
        }
        if ($this->booking->payment_plan_version === 2) {
            return app(BookingPaymentAccountingService::class)->summary($this->booking)['remaining_cents'] / 100;
        }

        return (float) $this->booking->net_amount;
    }

    public static function isDated(BookingItem $item): bool
    {
        return $item->check_in_date !== null && ($item->item_type !== 'room' || $item->check_out_date !== null);
    }

    /**
     * The planner itinerary or Lucky offer a booked service came from, when recorded.
     */
    public static function provenance(BookingItem $item): ?string
    {
        $snapshot = $item->item_snapshot ?? [];
        if (! empty($snapshot['trip_itinerary_id'])) {
            return 'itinerary:'.$snapshot['trip_itinerary_id'];
        }
        if (! empty($snapshot['lucky_offer_id'])) {
            return 'lucky:'.$snapshot['lucky_offer_id'];
        }

        return ! empty($snapshot['lucky_group_id']) ? 'lucky-group:'.$snapshot['lucky_group_id'] : null;
    }

    /**
     * Build trip windows: services that share a provenance stay together, and
     * overlapping or back-to-back services merge into one window. Undated
     * services join their provenance's window, or are listed separately.
     *
     * @param  Collection<int, BookingItem>  $included
     * @return array{0: array<string, array>, 1: Collection<int, BookingItem>, 2: bool}
     */
    private static function groups(Booking $booking, Collection $included): array
    {
        $intervals = [];
        $orphans = new EloquentCollection;
        foreach ($included->groupBy(fn (BookingItem $item) => self::provenance($item) ?? 'item:'.$item->id) as $key => $items) {
            $dated = $items->filter(fn (BookingItem $item) => self::isDated($item))->values();
            if ($dated->isEmpty()) {
                $orphans = $orphans->merge($items);

                continue;
            }
            $intervals[] = [
                'start' => $dated->map(fn (BookingItem $item) => self::serviceStart($item))->min(),
                'end' => $dated->map(fn (BookingItem $item) => self::serviceEnd($item))->max(),
                'items' => $dated,
                'undated' => $items->reject(fn (BookingItem $item) => self::isDated($item))->values(),
                'sources' => str_starts_with($key, 'item:') ? [] : [$key],
            ];
        }
        usort($intervals, fn (array $a, array $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        $windows = [];
        foreach ($intervals as $interval) {
            $last = array_key_last($windows);
            if ($last !== null && $interval['start']->lte($windows[$last]['end']->copy()->addDay())) {
                $windows[$last]['end'] = $windows[$last]['end']->max($interval['end']);
                $windows[$last]['items'] = $windows[$last]['items']->merge($interval['items']);
                $windows[$last]['undated'] = $windows[$last]['undated']->merge($interval['undated']);
                $windows[$last]['sources'] = array_values(array_unique([...$windows[$last]['sources'], ...$interval['sources']]));

                continue;
            }
            $windows[] = $interval;
        }

        // An admin-recorded extension lengthens the final trip window only.
        $last = array_key_last($windows);
        if ($last !== null && $booking->trip_end_override && $booking->trip_end_override->gt($windows[$last]['end'])) {
            $windows[$last]['end'] = $booking->trip_end_override->copy();
        }

        $groups = [];
        foreach ($windows as $window) {
            $window['items'] = $window['items']->sortBy(fn (BookingItem $item) => [self::serviceStart($item)->timestamp, $item->id])->values();
            $window['key'] = 'trip:'.$window['items']->merge($window['undated'])->min('id');
            $window['signature'] = hash('sha256', json_encode([
                $window['items']->merge($window['undated'])->merge($orphans)->sortBy('id')
                    ->map(fn (BookingItem $item) => [$item->id, $item->check_in_date?->toDateString(), $item->check_out_date?->toDateString(), $item->quantity])->values()->all(),
                $window['end']->toIso8601String(),
            ]));
            $groups[$window['key']] = $window;
        }
        $inferred = count($groups) > 1 && collect($groups)->contains(fn (array $group) => $group['sources'] === []);

        return [$groups, $orphans->sortBy('id')->values(), $inferred];
    }

    private static function serviceStart(BookingItem $item): Carbon
    {
        return Carbon::parse($item->check_in_date->toDateString().' 09:00', self::TIMEZONE);
    }

    private static function serviceEnd(BookingItem $item): Carbon
    {
        return Carbon::parse(($item->check_out_date ?? $item->check_in_date)->toDateString().' 23:59:59', self::TIMEZONE);
    }
}
