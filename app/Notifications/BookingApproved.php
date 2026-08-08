<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingApproved extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $deadline = $booking->payment_deadline
            ? $booking->payment_deadline->format('M j, Y \a\t g:i A')
            : 'soon';

        $mail = (new MailMessage)
            ->subject("Booking Approved — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Good news! All requested items for booking **{$booking->booking_code}** are available and your booking has been **approved**.")
            ->line("Please complete your payment of **₱" . number_format((float) $booking->net_amount, 2) . "** before **{$deadline}** to confirm your reservation.");

        if ((float) $booking->admin_discount_amount > 0 || (float) $booking->admin_surcharge_amount > 0) {
            $reason = $booking->price_adjustment_reason;

            if ((float) $booking->admin_discount_amount > 0) {
                $mail->line("We've applied a discount of **−₱" . number_format((float) $booking->admin_discount_amount, 2) . "** to your booking.");
            }
            if ((float) $booking->admin_surcharge_amount > 0) {
                $mail->line("An additional amount of **+₱" . number_format((float) $booking->admin_surcharge_amount, 2) . "** has been added to your booking.");
            }
            if ($reason) {
                $mail->line("Reason: {$reason}");
            }
        }

        return $mail
            ->line('Bookings not paid within 48 hours of approval will automatically expire.')
            ->action('Proceed to Payment', route('booking.pay', $booking->booking_code))
            ->line('Thank you for traveling with SunnyTrips!');
    }

    protected function message(): string
    {
        $deadline = $this->booking->payment_deadline
            ? $this->booking->payment_deadline->format('M j, g:i A')
            : '';

        return "Booking {$this->booking->booking_code} approved — payment due before {$deadline}.";
    }
}
