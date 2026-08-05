<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingExpired extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;

        return (new MailMessage)
            ->subject("Booking Expired — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Booking **{$booking->booking_code}** expired because payment was not completed within the 48-hour window.")
            ->line('Your reserved dates have been released back to inventory. You can rebook anytime.')
            ->action('Rebook Your Trip', route('booking.show', $booking->booking_code))
            ->line('Thank you. — SunnyTrips Team');
    }

    protected function message(): string
    {
        return "Booking {$this->booking->booking_code} expired — payment window passed, inventory released.";
    }
}
