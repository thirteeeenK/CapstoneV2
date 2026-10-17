<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\RoomType;
use App\Services\SystemSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class BookingPaymentPolicy
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    public static function cents(string $amount): int
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $amount)) {
            throw new PaymentGatewayException('The payment amount is invalid.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function initialWindowEnd(Booking $booking): Carbon
    {
        $hours = $booking->payment_policy['initial_window_hours'] ?? $this->settings->section('payments')['initial_window_hours'];

        return ($booking->created_at ?? now())->copy()->addHours((int) $hours);
    }

    /** @return array<string, mixed> */
    public function approvalData(Booking $booking, ?string $deadlineOverride = null, ?string $environment = null, ?Carbon $approvedAt = null): array
    {
        $environment = app(PaymentEnvironmentResolver::class)->mode();
        $settings = $this->settings->section('payments');
        if (($booking->payment_policy['request_policy_revision'] ?? null) === 3) {
            $settings['initial_window_hours'] = $booking->payment_policy['initial_window_hours'] ?? $settings['initial_window_hours'];
        }
        $now = $approvedAt?->copy() ?? now();
        $submitted = ($booking->created_at ?? $now)->copy()->timezone('Asia/Manila');
        $windowEnd = $submitted->copy()->addHours($settings['initial_window_hours']);
        $initial = $deadlineOverride ? Carbon::parse($deadlineOverride, 'Asia/Manila') : $windowEnd->copy();
        $eligibility = $this->depositEligibility($booking, (int) $settings['balance_lead_days'], $now, (int) $settings['undated_balance_days'], $submitted, (int) $settings['deposit_min_advance_days']);
        $start = $eligibility['start'];
        $balanceDue = $eligibility['balance_due'];
        $total = self::cents((string) $booking->net_amount);
        $eligible = $settings['installments_enabled'] && $eligibility['eligible'];
        if ($eligible && ! $deadlineOverride && $initial->gte($balanceDue)) {
            $eligible = false;
            $eligibility['explanation'] = 'The balance is due '.$balanceDue->format('M j, Y g:i A').' (Philippine time), before the '.$settings['initial_window_hours'].'-hour initial payment window can finish. Full payment is required under the default deadlines. An admin may review an earlier initial deadline before approval.';
        }
        $cutoff = $eligible ? $balanceDue : $start?->copy()->subMinute();
        if (! $deadlineOverride && $cutoff && $cutoff->lt($initial)) {
            $initial = $cutoff->copy();
        }
        if ($initial->gt($windowEnd)) {
            throw ValidationException::withMessages(['payment_deadline_override' => 'The initial deadline cannot exceed the payment window measured from reservation submission.']);
        }
        if ($eligible && $initial->gte($balanceDue)) {
            throw ValidationException::withMessages(['payment_deadline_override' => 'Choose an initial deadline strictly before the remaining balance deadline.']);
        }
        if ($initial->lte(now()) || ($start && $initial->gte($start))) {
            throw ValidationException::withMessages(['payment_deadline_override' => 'No valid payment window remains. The deadline must be in the future, within the submission payment window, and before check-in or the first confirmed service. Review or reschedule this request.']);
        }

        return [
            'payment_plan_version' => $settings['installments_enabled'] ? 2 : null,
            'payment_environment' => $environment,
            'payment_deadline' => $initial->copy()->timezone(config('app.timezone')),
            'deposit_required_cents' => intdiv($total + 1, 2),
            'balance_due_at' => $eligible ? $balanceDue : null,
            'payment_policy' => [
                'policy_revision' => 3,
                'request_policy_revision' => $booking->payment_policy['request_policy_revision'] ?? null,
                'legacy_arrival_accepted' => ! isset($booking->payment_policy['request_policy_revision']),
                'submitted_at' => $submitted->toIso8601String(),
                'initial_deadline_basis' => 'submission',
                'initial_window_ends_at' => $windowEnd->toIso8601String(),
                'shortened_window' => $initial->lt($windowEnd),
                'initial_window_hours' => $settings['initial_window_hours'],
                'deposit_min_advance_days' => $settings['deposit_min_advance_days'],
                'advance_days_at_submission' => $eligibility['advance_days'],
                'balance_lead_days' => $settings['balance_lead_days'],
                'undated_balance_days' => $settings['undated_balance_days'],
                'anchor_source' => $eligibility['anchor_source'],
                'balance_rule' => $start ? 'before_travel' : 'after_approval',
                'service_time_source' => $eligibility['time_source'],
                'settings_revision' => $this->settings->revision('payments'),
                'deposit_eligible' => (bool) $eligible, 'quote_amount' => $total,
                'deposit_explanation' => $eligibility['explanation'],
                'service_start' => $start?->toIso8601String(), 'timezone' => 'Asia/Manila', 'deposit_percent' => 50,
            ],
        ];
    }

    public function depositUnavailableReason(Booking $booking): ?string
    {
        if ($booking->payment_policy['deposit_eligible'] ?? false) {
            return null;
        }
        if ($booking->payment_policy['deposit_explanation'] ?? null) {
            return $booking->payment_policy['deposit_explanation'];
        }
        $details = $this->depositEligibility($booking, (int) ($booking->payment_policy['balance_lead_days'] ?? 7), $booking->approved_at ?? now(), (int) ($booking->payment_policy['undated_balance_days'] ?? 7));

        return $details['explanation'] ?: 'This booking was approved for full payment only. Its saved payment terms have not been changed by later settings updates.';
    }

    /** @return array{start: ?Carbon, balance_due: Carbon, eligible: bool, explanation: ?string, anchor_source: string, advance_days: ?int, time_source: string} */
    private function depositEligibility(Booking $booking, int $leadDays, Carbon $approvedAt, int $undatedDays, ?Carbon $submitted = null, ?int $advanceThreshold = null): array
    {
        $items = $booking->items()->where('availability_status', '!=', 'unavailable')->get();
        $anchors = collect();
        foreach ($booking->trip_itineraries ?? [] as $snapshot) {
            if (! empty($snapshot['start_date'])) {
                $anchors->push(['date' => Carbon::parse($snapshot['start_date'].' 09:00:00', 'Asia/Manila'), 'source' => 'confirmed_trip', 'time_source' => 'date_only_cutoff']);
            }
        }
        $rooms = RoomType::with('hotel')->whereIn('id', $items->where('item_type', 'room')->pluck('item_id'))->get()->keyBy('id');
        foreach ($items as $item) {
            if ($item->check_in_date && ($item->item_type !== 'activity' || ($item->item_snapshot['fixed_date'] ?? false))) {
                $time = $item->item_type === 'room' ? ($item->item_snapshot['check_in_time'] ?? $rooms->get($item->item_id)?->hotel?->check_in_time) : null;
                $hasTime = is_string($time) && preg_match('/^\d{2}:\d{2}(?::\d{2})?$/D', $time);
                $anchors->push(['date' => Carbon::parse($item->check_in_date->toDateString().' '.($hasTime ? $time : '09:00:00'), 'Asia/Manila'), 'source' => 'fixed_service', 'time_source' => $hasTime ? 'hotel_check_in' : 'date_only_cutoff']);
            }
        }
        $anchor = $anchors->sortBy(fn ($anchor) => $anchor['date']->timestamp)->first();
        $start = $anchor['date'] ?? null;
        $balanceDue = $start ? $start->copy()->subDays($leadDays) : $approvedAt->copy()->addDays($undatedDays);
        $advanceDays = $start && $submitted ? (int) $submitted->copy()->startOfDay()->diffInDays($start->copy()->startOfDay(), false) : null;
        $reasons = [];
        if ($advanceDays !== null && $advanceThreshold !== null && $advanceDays <= $advanceThreshold) {
            $reasons[] = 'Full payment is required: this reservation was submitted '.$advanceDays.' calendar day(s) before its confirmed booking date. A 50% deposit is offered only for bookings made more than '.$advanceThreshold.' days ahead (Philippine time).';
        }
        if ($items->isEmpty()) {
            $reasons[] = 'This booking has no included services.';
        }
        if ($balanceDue && $balanceDue->lte($approvedAt)) {
            $reasons[] = 'The '.$leadDays.'-day balance rule places the balance deadline at '.$balanceDue->format('M j, Y g:i A').' (Philippine time), before or at approval. Full payment is required for these near-term dates.';
        }
        if (! $start && $this->settings->section('payments')['installments_enabled'] && $undatedDays * 24 <= (int) $this->settings->section('payments')['initial_window_hours']) {
            throw ValidationException::withMessages(['undated_balance_days' => 'The flexible-date balance window must exceed the initial payment window.']);
        }
        if (self::cents((string) $booking->net_amount) < 200) {
            $reasons[] = 'The booking total must be at least ₱2.00 so both the deposit and balance meet the ₱1.00 minimum payment.';
        }

        return ['start' => $start, 'balance_due' => $balanceDue, 'eligible' => $reasons === [], 'explanation' => $reasons === [] ? null : implode(' ', $reasons), 'anchor_source' => $anchor['source'] ?? 'approval', 'advance_days' => $advanceDays, 'time_source' => $anchor['time_source'] ?? 'approval'];
    }

    public function assertCollectible(Booking $booking): void
    {
        app(PaymentEnvironmentResolver::class)->assertBooking($booking);
        if ($booking->payment_plan_version !== 2 || $booking->status !== Booking::STATUS_APPROVED
            || $booking->payment_status === Booking::PAYMENT_PAID || $booking->isPaymentDeadlinePassed()
            || $booking->balance_overdue_at
            || ($booking->payment_policy['quote_amount'] ?? null) !== self::cents((string) $booking->net_amount)) {
            throw new PaymentGatewayException('This booking cannot accept another payment right now. Contact support to review its deadline or quote.');
        }
    }
}
