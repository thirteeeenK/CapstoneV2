<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingRequestReceived extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;

        return (new MailMessage)
            ->subject("Booking Request Received — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("We received your booking request **{$booking->booking_code}** and it is now awaiting availability verification.")
            ->line("Amount to pay once approved: **₱" . number_format((float) $booking->net_amount, 2) . "**")
            ->line('Our team will verify your rooms, activities, and packages and notify you by email once the booking is approved.')
            ->action('View Booking', route('booking.show', $booking->booking_code))
            ->line('Thank you for traveling with SunnyTrips!');
    }

    protected function message(): string
    {
        return "Booking request {$this->booking->booking_code} received — awaiting availability verification.";
    }
}
