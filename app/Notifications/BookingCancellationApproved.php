<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingCancellationApproved extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $reason = $booking->cancellation_reason ?: $booking->cancellation_request_reason ?: 'Approved by SunnyTrips admin.';

        return (new MailMessage)
            ->subject("Cancellation Approved — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Your cancellation request for booking **{$booking->booking_code}** has been **approved**.")
            ->line("Reason: {$reason}")
            ->line('No payment was taken for this booking. You may rebook at any time.')
            ->action('View Booking', route('booking.show', $booking->booking_code))
            ->line('Thank you. — SunnyTrips Team');
    }

    protected function message(): string
    {
        $extra = $this->booking->cancellation_reason ?: $this->booking->cancellation_request_reason;

        return "Cancellation approved for {$this->booking->booking_code}".($extra ? ": {$extra}" : '.');
    }
}
