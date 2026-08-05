<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingRejected extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $reason = $booking->rejection_reason ?: 'Requested items are no longer available for your selected dates.';

        return (new MailMessage)
            ->subject("Booking Request Declined — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("We're sorry, but booking request **{$booking->booking_code}** could not be approved.")
            ->line("Reason: **{$reason}**")
            ->line('You can rebook with different dates or items — no payment was taken.')
            ->action('View Booking', route('booking.show', $booking->booking_code))
            ->line('Thank you for understanding. — SunnyTrips Team');
    }

    protected function message(): string
    {
        return "Booking {$this->booking->booking_code} was declined: " . ($this->booking->rejection_reason ?: 'unavailable for selected dates.');
    }
}
