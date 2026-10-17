<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentRequest;
use App\Services\AdminNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GoTymePaymentService
{
    public function initialize(Booking $booking): Booking
    {
        return app(PaymentService::class)->withBookingLock($booking->id, fn (): Booking => DB::transaction(function () use ($booking): Booking {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->ensureApproved($lockedBooking);
            if ($lockedBooking->payment_plan_version === 2) {
                app(BookingPaymentPolicy::class)->assertCollectible($lockedBooking);
            }
            app(PaymentService::class)->assertCanSelectGateway($lockedBooking, 'gotyme');

            $lockedBooking->forceFill([
                'payment_environment' => app(PaymentEnvironmentResolver::class)->mode(),
                'gateway' => 'gotyme',
                'gateway_reference' => 'GOTYME-'.$lockedBooking->booking_code,
                'payment_method' => 'gotyme',
                'payment_url' => null,
                'gateway_data' => array_merge($lockedBooking->gateway_data ?? [], [
                    'manual_verification' => true,
                ]),
            ])->save();

            return $lockedBooking;
        }));
    }

    public function submit(Booking $booking, string $senderReference, string $claimedAmount): BookingPayment
    {
        try {
            return app(PaymentService::class)->withBookingLock($booking->id, fn (): BookingPayment => DB::transaction(function () use ($booking, $senderReference, $claimedAmount): BookingPayment {
                $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
                $this->ensureApproved($lockedBooking);
                $collection = null;
                if ($lockedBooking->payment_plan_version === 2) {
                    app(BookingPaymentPolicy::class)->assertCollectible($lockedBooking);
                    $collection = BookingPaymentRequest::where('booking_id', $lockedBooking->id)
                        ->where('gateway', 'gotyme')->whereIn('status', ['creating', 'pending'])->lockForUpdate()->first();
                    if (! $collection) {
                        throw new PaymentGatewayException('Choose the amount to pay before submitting a GoTyme transfer.');
                    }
                }
                app(PaymentService::class)->assertCanSelectGateway($lockedBooking, 'gotyme');

                $trimmedReference = trim($senderReference);
                $payment = $lockedBooking->payments()->create([
                    'provider' => 'gotyme',
                    'booking_payment_request_id' => $collection?->id,
                    'sender_reference' => $trimmedReference,
                    'normalized_reference' => $this->normalizeReference($trimmedReference),
                    'claimed_amount' => $claimedAmount,
                    'status' => BookingPayment::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                ]);

                $collection?->update(['status' => 'awaiting_verification']);

                $lockedBooking->forceFill([
                    'payment_environment' => app(PaymentEnvironmentResolver::class)->mode(),
                    'gateway' => 'gotyme',
                    'gateway_reference' => 'GOTYME-'.$lockedBooking->booking_code,
                    'payment_method' => 'gotyme',
                    'payment_reference' => $trimmedReference,
                    'payment_url' => null,
                    'gateway_data' => array_merge($lockedBooking->gateway_data ?? [], [
                        'manual_verification' => true,
                        'sender_ref' => $trimmedReference,
                        'claimed_amount' => $claimedAmount,
                        'submitted_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                app(AdminNotificationService::class)->send('payment_submitted', $lockedBooking);

                return $payment;
            }));
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505' || str_contains($exception->getMessage(), 'booking_payments_provider_normalized_reference_unique')) {
                throw ValidationException::withMessages([
                    'sender_ref' => 'This GoTyme transaction reference has already been submitted.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @return array{completed: bool, verified_cents: int, remaining_cents: int, overpayment_cents: int}
     */
    public function verify(Booking $booking, int $paymentId, string $verifiedAmount, AdminModel $admin, ?string $adminNote = null): array
    {
        return DB::transaction(function () use ($booking, $paymentId, $verifiedAmount, $admin, $adminNote): array {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->ensureApproved($lockedBooking);

            $payment = BookingPayment::query()
                ->where('booking_id', $lockedBooking->getKey())
                ->where('provider', 'gotyme')
                ->lockForUpdate()
                ->find($paymentId);

            if (! $payment || $payment->status !== BookingPayment::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'booking_payment_id' => 'Select a pending GoTyme transfer that has not been verified yet.',
                ]);
            }

            $payment->forceFill([
                'verified_amount' => $verifiedAmount,
                'status' => BookingPayment::STATUS_VERIFIED,
                'verified_at' => now(),
                'verified_by_admin_id' => $admin->getKey(),
                'admin_note' => filled($adminNote) ? trim($adminNote) : null,
            ])->save();

            $summary = $this->summary($lockedBooking);
            if ($payment->booking_payment_request_id) {
                $collection = BookingPaymentRequest::findOrFail($payment->booking_payment_request_id);
                app(BookingPaymentAccountingService::class)->receipt($collection, 'manual-'.$payment->id, BookingPaymentPolicy::cents($verifiedAmount), null, $admin);
                $summary = app(BookingPaymentAccountingService::class)->summary($lockedBooking->fresh());

                return ['completed' => $summary['remaining_cents'] === 0] + $summary;
            }
            $completed = $summary['remaining_cents'] === 0;

            if ($completed) {
                $lockedBooking->transitionTo(
                    Booking::STATUS_PAID,
                    [Booking::STATUS_APPROVED],
                    [
                        'paid_at' => now(),
                        'payment_status' => Booking::PAYMENT_PAID,
                        'payment_method' => 'gotyme',
                        'payment_reference' => $payment->sender_reference,
                    ],
                    'GoTyme transfer(s) verified by admin.',
                    $admin
                );
            } else {
                $lockedBooking->forceFill([
                    'payment_status' => Booking::PAYMENT_PARTIAL,
                    'payment_method' => 'gotyme',
                    'payment_reference' => $payment->sender_reference,
                ])->save();
            }

            return ['completed' => $completed] + $summary;
        });
    }

    /**
     * @return array{due_cents: int, verified_cents: int, remaining_cents: int, overpayment_cents: int, pending_claimed_cents: int}
     */
    public function summary(Booking $booking): array
    {
        if ($booking->payment_plan_version === 2) {
            return app(BookingPaymentAccountingService::class)->summary($booking);
        }
        $verified = (string) $booking->payments()
            ->where('provider', 'gotyme')
            ->where('status', BookingPayment::STATUS_VERIFIED)
            ->sum('verified_amount');
        $pendingClaimed = (string) $booking->payments()
            ->where('provider', 'gotyme')
            ->where('status', BookingPayment::STATUS_SUBMITTED)
            ->sum('claimed_amount');

        $dueCents = $this->toCents((string) $booking->net_amount);
        $verifiedCents = $this->toCents($verified);

        return [
            'due_cents' => $dueCents,
            'verified_cents' => $verifiedCents,
            'remaining_cents' => max($dueCents - $verifiedCents, 0),
            'overpayment_cents' => max($verifiedCents - $dueCents, 0),
            'pending_claimed_cents' => $this->toCents($pendingClaimed),
        ];
    }

    public function hasActiveTransfer(Booking $booking): bool
    {
        if ($booking->payment_plan_version === 2) {
            return BookingPaymentRequest::where('booking_id', $booking->id)->where('gateway', 'gotyme')
                ->whereIn('status', BookingPaymentRequest::ACTIVE)->exists();
        }

        return $booking->payments()
            ->where('provider', 'gotyme')
            ->whereIn('status', [BookingPayment::STATUS_SUBMITTED, BookingPayment::STATUS_VERIFIED])
            ->exists();
    }

    private function ensureApproved(Booking $booking): void
    {
        app(PaymentEnvironmentResolver::class)->assertBooking($booking);
        if ($booking->status !== Booking::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'payment' => 'Only approved bookings awaiting payment can use GoTyme.',
            ]);
        }
    }

    private function normalizeReference(string $reference): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $reference));
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        return ((int) $whole * 100) + (int) $fraction;
    }
}
