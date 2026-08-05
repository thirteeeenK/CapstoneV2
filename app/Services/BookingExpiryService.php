<?php

namespace App\Services;

use App\Models\Booking;
use App\Notifications\BookingExpired;

class BookingExpiryService
{
    /**
     * Expire an approved booking whose payment window has passed.
     *
     * @return bool true when the booking was expired
     */
    public function expireIfDue(Booking $booking): bool
    {
        if (!$booking->isPaymentDeadlinePassed()) {
            return false;
        }

        $booking->expired_at = now();
        $booking->markStatus(Booking::STATUS_EXPIRED, 'Payment window expired 48 hours after approval.');

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
