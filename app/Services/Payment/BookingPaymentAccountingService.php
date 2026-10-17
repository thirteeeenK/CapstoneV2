<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Models\BookingPaymentTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BookingPaymentAccountingService
{
    /** @return array{due_cents: int, verified_cents: int, remaining_cents: int, overpayment_cents: int, pending_claimed_cents: int} */
    public function summary(Booking $booking): array
    {
        $rows = BookingPaymentTransaction::where('booking_id', $booking->id)
            ->where('livemode', $booking->payment_environment !== 'test')->where('allocated', true)->get(['amount', 'kind']);
        $credit = $rows->sum(fn ($row) => $row->kind === 'receipt' ? $row->amount : -$row->amount);
        $due = BookingPaymentPolicy::cents((string) $booking->net_amount);

        return [
            'due_cents' => $due, 'verified_cents' => $credit,
            'remaining_cents' => max(0, $due - $credit), 'overpayment_cents' => max(0, $credit - $due),
            'pending_claimed_cents' => BookingPaymentPolicy::cents((string) $booking->payments()->where('status', 'submitted')->sum('claimed_amount')),
        ];
    }

    public function receipt(BookingPaymentRequest $request, string $reference, int $amount, ?Carbon $occurredAt = null, ?AdminModel $admin = null): BookingPaymentTransaction
    {
        return DB::transaction(function () use ($request, $reference, $amount, $occurredAt, $admin): BookingPaymentTransaction {
            $booking = Booking::query()->lockForUpdate()->findOrFail($request->booking_id);
            $request = BookingPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
            $existing = BookingPaymentTransaction::where('gateway', $request->gateway)->where('livemode', $request->livemode)->where('source_reference', $reference)->first();
            if ($existing) {
                if ($existing->booking_payment_request_id !== $request->id || $existing->amount !== $amount) {
                    throw new PaymentGatewayException('This payment reference is already associated with another collection.');
                }

                return $existing;
            }
            if ($amount <= 0 || ($request->gateway !== 'gotyme' && $request->gateway !== 'cash' && $amount !== $request->amount)) {
                throw new PaymentGatewayException('The confirmed amount does not match this payment request.');
            }
            $paidAt = $occurredAt ?? now();
            $allocated = $booking->status === Booking::STATUS_APPROVED
                && $request->livemode === app(PaymentEnvironmentResolver::class)->isLive()
                && $booking->payment_status !== Booking::PAYMENT_PAID
                && $request->status !== 'cancelled'
                && $request->quote_amount === BookingPaymentPolicy::cents((string) $booking->net_amount)
                && $request->livemode === ($booking->payment_environment !== 'test')
                && $paidAt->lte($request->due_at) && $this->summary($booking)['remaining_cents'] > 0;
            $transaction = BookingPaymentTransaction::create([
                'booking_id' => $booking->id, 'booking_payment_request_id' => $request->id,
                'gateway' => $request->gateway, 'livemode' => $request->livemode, 'source_reference' => $reference,
                'kind' => 'receipt', 'amount' => $amount, 'currency' => 'PHP', 'allocated' => $allocated,
                'occurred_at' => $paidAt, 'verified_by_admin_id' => $admin?->id,
            ]);
            $request->update(['status' => $allocated ? 'succeeded' : 'review_required', 'confirmed_at' => now()]);
            if ($allocated) {
                $this->applyCredit($booking, $request, $reference, $admin);
            }
            DB::table('booking_payment_notices')->insertOrIgnore([
                'booking_id' => $booking->id, 'booking_payment_transaction_id' => $transaction->id,
                'dedupe_key' => 'receipt-'.$transaction->id, 'kind' => $allocated ? ($booking->fresh()->payment_status === 'paid' ? 'fully_paid' : 'receipt') : 'review',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $transaction;
        });
    }

    public function recordRefund(Booking $booking, BookingPaymentTransaction $receipt, int $amount, string $reference, AdminModel $admin, string $note): void
    {
        DB::transaction(function () use ($booking, $receipt, $amount, $reference, $admin, $note): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $receipt = BookingPaymentTransaction::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->booking_id !== $booking->id || $receipt->kind !== 'receipt') {
                throw new PaymentGatewayException('Select a credited payment belonging to this booking.');
            }
            $duplicate = BookingPaymentTransaction::where('gateway', $receipt->gateway)->where('livemode', $receipt->livemode)->where('source_reference', $reference)->first();
            if ($duplicate) {
                if ($duplicate->original_transaction_id !== $receipt->id || $duplicate->amount !== $amount) {
                    throw new PaymentGatewayException('The refund reference is already in use.');
                }

                return;
            }
            $refunded = BookingPaymentTransaction::where('original_transaction_id', $receipt->id)->sum('amount');
            if ($amount <= 0 || $amount + $refunded > $receipt->amount) {
                throw new PaymentGatewayException('The refund exceeds the unrefunded amount.');
            }
            BookingPaymentTransaction::create([
                'booking_id' => $booking->id, 'original_transaction_id' => $receipt->id,
                'gateway' => $receipt->gateway, 'livemode' => $receipt->livemode, 'source_reference' => $reference,
                'kind' => 'refund', 'amount' => $amount, 'allocated' => $receipt->allocated, 'occurred_at' => now(),
                'verified_by_admin_id' => $admin->id, 'note' => $note,
            ]);
            if (! $receipt->allocated) {
                if ($amount + $refunded === $receipt->amount) {
                    BookingPaymentRequest::whereKey($receipt->booking_payment_request_id)->where('status', 'review_required')->update(['status' => 'cancelled']);
                }

                return;
            }
            $remaining = $this->summary($booking);
            $booking->forceFill([
                'payment_status' => $remaining['verified_cents'] === 0 ? Booking::PAYMENT_REFUNDED : Booking::PAYMENT_PARTIAL,
                'balance_overdue_at' => now(),
                'deposit_secured_at' => $remaining['verified_cents'] >= $booking->deposit_required_cents ? $booking->deposit_secured_at : null,
            ])->save();
        });
    }

    private function applyCredit(Booking $booking, BookingPaymentRequest $request, string $reference, ?AdminModel $admin): void
    {
        $summary = $this->summary($booking);
        if ($summary['remaining_cents'] === 0) {
            $booking->transitionTo(Booking::STATUS_PAID, [Booking::STATUS_APPROVED], [
                'payment_status' => Booking::PAYMENT_PAID, 'paid_at' => now(), 'balance_overdue_at' => null,
                'payment_reference' => $reference, 'payment_method' => $request->gateway,
            ], 'All booking payments have been verified.', $admin ?? 'system');
        } else {
            $secured = ($booking->payment_policy['deposit_eligible'] ?? false)
                && $summary['verified_cents'] >= $booking->deposit_required_cents;
            $booking->forceFill([
                'payment_status' => Booking::PAYMENT_PARTIAL, 'payment_reference' => $reference,
                'payment_method' => $request->gateway, 'deposit_secured_at' => $secured ? ($booking->deposit_secured_at ?? now()) : null,
            ])->save();
            $booking->history()->create([
                'from_status' => $booking->status, 'to_status' => $booking->status,
                'note' => $secured ? 'Reservation deposit verified; balance remains due.' : 'Partial payment verified; reservation deposit is not yet covered.',
                'actor_type' => $admin ? AdminModel::class : 'system', 'actor_id' => $admin?->id,
            ]);
        }

    }

    public function allocateReviewedReceipt(BookingPaymentTransaction $transaction, AdminModel $admin, string $reason): void
    {
        DB::transaction(function () use ($transaction, $admin, $reason): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($transaction->booking_id);
            $transaction = BookingPaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->allocated) {
                return;
            }
            app(BookingPaymentPolicy::class)->assertCollectible($booking);
            $request = BookingPaymentRequest::query()->lockForUpdate()->findOrFail($transaction->booking_payment_request_id);
            if ($transaction->kind !== 'receipt' || $request->status !== 'review_required'
                || $request->quote_amount !== BookingPaymentPolicy::cents((string) $booking->net_amount)
                || $request->livemode !== ($booking->payment_environment !== 'test')
                || BookingPaymentTransaction::where('original_transaction_id', $transaction->id)->exists()) {
                throw new PaymentGatewayException('This receipt cannot be allocated. Review its quote, environment and any refunds.');
            }
            $transaction->update(['allocated' => true, 'verified_by_admin_id' => $admin->id, 'note' => $reason]);
            $request->update(['status' => 'succeeded']);
            $this->applyCredit($booking, $request, $transaction->source_reference, $admin);
            DB::table('booking_payment_notices')->insertOrIgnore([
                'booking_id' => $booking->id, 'booking_payment_transaction_id' => $transaction->id,
                'dedupe_key' => 'allocated-'.$transaction->id, 'kind' => $booking->fresh()->payment_status === 'paid' ? 'fully_paid' : 'receipt',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function collectedBetween(Carbon $from, Carbon $to, ?string $status = null): float
    {
        $new = BookingPaymentTransaction::where('allocated', true)->where('livemode', true)
            ->whereBetween('occurred_at', [$from->toIso8601String(), $to->toIso8601String()])
            ->when($status, fn ($query) => $query->whereHas('booking', function ($bookings) use ($status): void {
                $status === 'closed' ? $bookings->whereIn('status', ['rejected', 'cancelled', 'expired', 'completed', 'cancellation_denied']) : $bookings->where('status', $status);
            }))
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END), 0) as cents")->value('cents');
        $legacy = Booking::whereNull('payment_plan_version')->where('payment_status', Booking::PAYMENT_PAID)
            ->where(fn ($query) => $query->whereNull('payment_environment')->orWhere('payment_environment', 'live'))
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$from, $to])
            ->when($status, function ($query) use ($status): void {
                $status === 'closed' ? $query->whereIn('status', ['rejected', 'cancelled', 'expired', 'completed', 'cancellation_denied']) : $query->where('status', $status);
            })->sum('net_amount');

        return ((int) $new / 100) + (float) $legacy;
    }

    /** @return array<string, float> */
    public function collectionSeries(Carbon $from, Carbon $to, ?string $status = null): array
    {
        $scope = function ($query) use ($status): void {
            if ($status === 'closed') {
                $query->whereIn('status', ['rejected', 'cancelled', 'expired', 'completed', 'cancellation_denied']);
            } elseif ($status) {
                $query->where('status', $status);
            }
        };
        $result = [];
        $ledger = BookingPaymentTransaction::where('allocated', true)->where('livemode', true)->whereBetween('occurred_at', [$from->toIso8601String(), $to->toIso8601String()])
            ->whereHas('booking', $scope)->selectRaw("DATE(occurred_at AT TIME ZONE 'Asia/Manila') AS day, SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END) AS cents")->groupByRaw("DATE(occurred_at AT TIME ZONE 'Asia/Manila')")->get();
        foreach ($ledger as $row) {
            $result[(string) $row->day] = (int) $row->cents / 100;
        }
        $legacy = Booking::whereNull('payment_plan_version')->where('payment_status', Booking::PAYMENT_PAID)
            ->where(fn ($query) => $query->whereNull('payment_environment')->orWhere('payment_environment', 'live'))
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$from, $to])->where($scope)
            ->selectRaw('DATE(COALESCE(paid_at, created_at)) AS day, SUM(net_amount) AS collected')->groupByRaw('DATE(COALESCE(paid_at, created_at))')->get();
        foreach ($legacy as $row) {
            $result[(string) $row->day] = ($result[(string) $row->day] ?? 0) + (float) $row->collected;
        }

        return $result;
    }

    public function outstandingFor(Builder $query): float
    {
        return (float) (clone $query)->selectRaw("COALESCE(SUM(CASE WHEN payment_plan_version = 2 THEN GREATEST(net_amount - COALESCE((SELECT SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END) FROM booking_payment_transactions WHERE booking_id = bookings.id AND allocated = true AND livemode = true),0)/100.0,0) ELSE net_amount END),0) AS outstanding")->value('outstanding');
    }
}
