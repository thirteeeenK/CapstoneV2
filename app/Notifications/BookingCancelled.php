<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingCancelled extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;

        return (new MailMessage)
            ->subject("Booking Cancelled — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Booking **{$booking->booking_code}** has been cancelled.")
            ->line('No payment was taken for this booking. You may rebook at any time.')
            ->action('View Booking', route('booking.show', $booking->booking_code))
            ->line('Thank you. — SunnyTrips Team');
    }

    protected function message(): string
    {
        return "Booking {$this->booking->booking_code} has been cancelled.";
    }
}
