<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingPaid extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;

        return (new MailMessage)
            ->subject("Payment Confirmed — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Your payment for booking **{$booking->booking_code}** has been confirmed. Your reservation is now **paid and confirmed**.")
            ->line('Amount paid: **₱'.number_format((float) $booking->net_amount, 2).'**')
            ->line('Your official booking voucher is available below.')
            ->action('View Booking Voucher', route('booking.show', $booking->booking_code))
            ->line('We look forward to hosting you. Thank you for traveling with SunnyTrips!');
    }

    protected function message(): string
    {
        return "Payment confirmed for booking {$this->booking->booking_code} — reservation is now confirmed.";
    }
}
