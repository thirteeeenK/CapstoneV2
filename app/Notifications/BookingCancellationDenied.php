<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingCancellationDenied extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $reason = $booking->cancellation_reason ?: 'Your booking remains active.';

        return (new MailMessage)
            ->subject("Cancellation Request Denied — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Your cancellation request for booking **{$booking->booking_code}** was **not approved**.")
            ->line("Admin message: **{$reason}**")
            ->line('Your booking remains active and your reserved dates are still held. No action is needed unless you wish to contact support.')
            ->action('View Booking', route('booking.show', $booking->booking_code))
            ->line('Thank you. — SunnyTrips Team');
    }

    protected function message(): string
    {
        return "Cancellation denied for {$this->booking->booking_code}: ".($this->booking->cancellation_reason ?: 'remains active.');
    }
}
