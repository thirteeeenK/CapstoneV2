<?php

namespace App\Services;

use App\Models\Booking;
use App\Notifications\BookingExpired;

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
        if (!$booking->isPaymentDeadlinePassed()) {
            return false;
        }

        if (!$booking->transitionTo(
            Booking::STATUS_EXPIRED,
            [Booking::STATUS_APPROVED],
            ['expired_at' => now()],
            'Payment window expired 48 hours after approval.'
        )) {
            return false;
        }

        $user = $booking->user;
        if ($user) {
            $user->notify(new BookingExpired($booking));
        } else {
            \Illuminate\Support\Facades\Notification::route('mail', $booking->contact_email)
                ->notify(new BookingExpired($booking));
        }
        return true;
    }
}
