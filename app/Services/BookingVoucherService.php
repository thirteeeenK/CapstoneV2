<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\Payment\GoTymePaymentService;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;

/**
 * Builds the downloadable travel voucher: a laid-out PDF (not a page capture) holding the
 * booking reference, traveler and trip facts, the service and payment breakdown, and any
 * saved itinerary with its distance and weather decisions.
 */
class BookingVoucherService
{
    /** Statuses whose booking has been reviewed and still stands, so a document is meaningful. */
    public const AVAILABLE_STATUSES = [
        Booking::STATUS_APPROVED,
        Booking::STATUS_PAID,
        Booking::STATUS_COMPLETED,
        Booking::STATUS_CANCELLATION_REQUESTED,
        Booking::STATUS_CANCELLATION_DENIED,
    ];

    public function __construct(private readonly GoTymePaymentService $payments) {}

    public function isAvailable(Booking $booking): bool
    {
        return in_array($booking->status, self::AVAILABLE_STATUSES, true);
    }

    /**
     * @return array{booking: Booking, document: array{title: string, subtitle: string, badge: string, tone: string, confirmed: bool}, summary: array<string, int>, deadline: array{label: string, at: Carbon}|null, paymentHistory: array<int, array{date: ?Carbon, method: string, reference: string, kind: string, amount_cents: int, note: ?string}>, trip: array{destination: ?string, start: ?Carbon, end: ?Carbon, nights: ?int, travelers: int}, itineraries: array<int, array<string, mixed>>, issuedAt: Carbon}
     */
    public function viewData(Booking $booking): array
    {
        $booking->loadMissing(['items', 'payments', 'paymentTransactions']);
        $summary = $this->payments->summary($booking);
        if ($booking->payment_plan_version !== 2 && $booking->payment_status === Booking::PAYMENT_PAID && $summary['remaining_cents'] > 0) {
            // Legacy (v1) gateway payments are recorded on the booking itself, not as ledger rows.
            $summary = ['verified_cents' => $summary['due_cents'], 'remaining_cents' => 0] + $summary;
        }

        return [
            'booking' => $booking,
            'document' => $this->document($booking),
            'summary' => $summary,
            'deadline' => $this->deadline($booking, $summary),
            'paymentHistory' => $this->paymentHistory($booking),
            'trip' => $this->trip($booking),
            'itineraries' => array_values(array_filter($booking->trip_itineraries ?? [], 'is_array')),
            'issuedAt' => now('Asia/Manila'),
        ];
    }

    /**
     * Money has secured the reservation, so the document is a travel voucher rather than a summary.
     */
    public function isConfirmed(Booking $booking): bool
    {
        return in_array($booking->payment_status, [Booking::PAYMENT_PAID, Booking::PAYMENT_PARTIAL], true)
            || $booking->deposit_secured_at !== null;
    }

    public function pdf(Booking $booking): DomPdf
    {
        return TravelDocumentPdf::render('booking.voucher', $this->viewData($booking) + ['webView' => false]);
    }

    public function filename(Booking $booking): string
    {
        return 'sunnytrips-'.$booking->booking_code.'-voucher.pdf';
    }

    /**
     * A booking only becomes a travel voucher once money secures it; before that it is a summary.
     *
     * @return array{title: string, subtitle: string, badge: string, tone: string, confirmed: bool}
     */
    private function document(Booking $booking): array
    {
        $confirmed = $this->isConfirmed($booking);

        [$badge, $tone] = match (true) {
            $booking->status === Booking::STATUS_COMPLETED => ['Trip completed', 'green'],
            $booking->status === Booking::STATUS_CANCELLATION_REQUESTED => ['Cancellation requested', 'amber'],
            $booking->payment_status === Booking::PAYMENT_REFUNDED => ['Refunded', 'slate'],
            $booking->payment_status === Booking::PAYMENT_PAID => ['Paid in full', 'green'],
            $confirmed => ['Reserved · Partially paid', 'blue'],
            default => ['Awaiting payment', 'amber'],
        };

        return [
            'title' => $confirmed ? 'Travel Voucher' : 'Booking Summary',
            'subtitle' => $confirmed
                ? 'Present this voucher, printed or on your phone, when you check in and join your activities.'
                : 'Not yet confirmed. This becomes your travel voucher once your payment is verified.',
            'badge' => $badge,
            'tone' => $tone,
            'confirmed' => $confirmed,
        ];
    }

    /**
     * The next payment the traveler owes, if any.
     *
     * @param  array<string, int>  $summary
     * @return array{label: string, at: Carbon}|null
     */
    private function deadline(Booking $booking, array $summary): ?array
    {
        if ($summary['remaining_cents'] <= 0 || ! in_array($booking->status, [Booking::STATUS_APPROVED, Booking::STATUS_CANCELLATION_DENIED], true)) {
            return null;
        }
        $at = $booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline;

        return $at ? ['label' => $booking->deposit_secured_at ? 'Remaining balance due' : 'Payment due', 'at' => $at->copy()->timezone('Asia/Manila')] : null;
    }

    /**
     * @return array<int, array{date: ?Carbon, method: string, reference: string, kind: string, amount_cents: int, note: ?string}>
     */
    private function paymentHistory(Booking $booking): array
    {
        if ($booking->payment_plan_version === 2) {
            return $booking->paymentTransactions->map(fn ($transaction) => [
                'date' => $transaction->occurred_at?->copy()->timezone('Asia/Manila'),
                'method' => ucfirst((string) $transaction->gateway).($transaction->livemode ? '' : ' (sandbox)'),
                'reference' => (string) $transaction->source_reference,
                'kind' => $transaction->kind === 'refund' ? 'Refund' : 'Payment',
                'amount_cents' => (int) $transaction->amount,
                'note' => $transaction->allocated ? null : 'Received; awaiting review, not credited yet',
            ])->values()->all();
        }

        $verified = $booking->payments->where('status', BookingPayment::STATUS_VERIFIED)->sortBy('id')->map(fn ($payment) => [
            'date' => ($payment->verified_at ?? $payment->submitted_at)?->copy()->timezone('Asia/Manila'),
            'method' => ucfirst((string) $payment->provider),
            'reference' => (string) $payment->sender_reference,
            'kind' => 'Payment',
            'amount_cents' => (int) round((float) $payment->verified_amount * 100),
            'note' => null,
        ])->values()->all();

        if ($verified === [] && $booking->paid_at) {
            return [[
                'date' => $booking->paid_at->copy()->timezone('Asia/Manila'),
                'method' => ucfirst((string) ($booking->payment_method ?: $booking->gateway ?: 'Online payment')),
                'reference' => (string) ($booking->gateway_reference ?: $booking->payment_reference ?: '—'),
                'kind' => 'Payment',
                'amount_cents' => (int) round((float) $booking->net_amount * 100),
                'note' => null,
            ]];
        }

        return $verified;
    }

    /**
     * @return array{destination: ?string, start: ?Carbon, end: ?Carbon, nights: ?int, travelers: int}
     */
    private function trip(Booking $booking): array
    {
        $itinerary = collect($booking->trip_itineraries ?? [])->first(fn ($snapshot) => is_array($snapshot));
        $items = $booking->items->where('availability_status', '!=', 'unavailable');
        $start = $items->pluck('check_in_date')->filter()->min();
        $end = $items->map(fn ($item) => $item->check_out_date ?? $item->check_in_date)->filter()->max();
        if ($itinerary) {
            $start = Carbon::parse($itinerary['start_date']);
            $end = Carbon::parse($itinerary['end_date']);
        }
        $destination = $itinerary['destination']
            ?? $items->map(fn ($item) => $item->item_snapshot['location_name'] ?? null)->filter()->first();
        $manifestCount = is_array($booking->guest_manifest) ? count($booking->guest_manifest) : 0;

        return [
            'destination' => $destination,
            'start' => $start ? Carbon::parse($start) : null,
            'end' => $end ? Carbon::parse($end) : null,
            'nights' => $start && $end ? (int) Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) : null,
            'travelers' => max($manifestCount, (int) ($itinerary['pax'] ?? 0), (int) $items->max('selected_pax')),
        ];
    }
}
