<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Models\BookingPaymentTransaction;
use App\Notifications\BookingExpired;
use App\Notifications\BookingNotification;
use App\Services\Payment\InstallmentPaymentService;
use App\Services\Payment\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BookingExpiryService
{
    /**
     * Expire an approved booking whose payment window has passed.
     *
     * The approved→expired transition is atomic, so concurrent requests
     * cannot double-expire or expire an already-paid booking; the customer
     * is only notified by the caller that actually performed the transition.
     *
     * @return bool true when this call expired the booking
     */
    public function expireIfDue(Booking $booking): bool
    {
        if ($booking->payment_plan_version === 2) {
            return $this->expireInstallmentIfDue($booking);
        }
        if (! $booking->isPaymentDeadlinePassed()) {
            return false;
        }

        if (! $booking->transitionTo(
            Booking::STATUS_EXPIRED,
            [Booking::STATUS_APPROVED],
            ['expired_at' => now()],
            'The agreed payment deadline passed without confirmed payment.',
            'system'
        )) {
            return false;
        }

        $user = $booking->user;
        if ($user) {
            $user->notify(new BookingExpired($booking));
        } else {
            Notification::route('mail', $booking->contact_email)
                ->notify(new BookingExpired($booking));
        }

        return true;
    }

    private function expireInstallmentIfDue(Booking $booking): bool
    {
        if (! $booking->isPaymentDeadlinePassed()) {
            return false;
        }
        $active = BookingPaymentRequest::where('booking_id', $booking->id)->whereIn('status', BookingPaymentRequest::ACTIVE)->get();
        foreach ($active as $collection) {
            try {
                app(InstallmentPaymentService::class)->closeUnpaid($collection);
            } catch (PaymentGatewayException|ConnectionException) {
                DB::transaction(function () use ($booking): void {
                    $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                    if ($current->isPaymentDeadlinePassed()) {
                        $current->forceFill(['balance_overdue_at' => $current->balance_overdue_at ?? now()])->save();
                    }
                });

                return false;
            }
        }

        return DB::transaction(function () use ($booking): bool {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! $booking->isPaymentDeadlinePassed()) {
                return false;
            }
            $hasMoney = BookingPaymentTransaction::where('booking_id', $booking->id)->exists();
            $unresolved = BookingPaymentRequest::where('booking_id', $booking->id)->whereIn('status', BookingPaymentRequest::ACTIVE)->exists();
            if ($hasMoney || $unresolved || $booking->deposit_secured_at) {
                $booking->forceFill(['balance_overdue_at' => $booking->balance_overdue_at ?? now()])->save();

                return false;
            }
            $expired = $booking->transitionTo(Booking::STATUS_EXPIRED, [Booking::STATUS_APPROVED], ['expired_at' => now()], 'The initial payment deadline passed without payment.', 'system');
            if ($expired) {
                DB::afterCommit(fn () => BookingNotification::send($booking, new BookingExpired($booking)));
            }

            return $expired;
        });
    }
}
