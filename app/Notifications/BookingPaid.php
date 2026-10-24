<?php

namespace App\Notifications;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingPaid extends BookingNotification
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;

        $message = (new MailMessage)
            ->subject("Payment Confirmed — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Your payment for booking **{$booking->booking_code}** has been confirmed. Your reservation is now **paid and confirmed**.")
            ->line('Amount paid: **₱'.number_format((float) $booking->net_amount, 2).'**')
            ->line('Your official booking voucher is available below.')
            ->action('View Booking Voucher', route('booking.show', $booking->booking_code))
            ->line('We look forward to hosting you. Thank you for traveling with SunnyTrips!');
        if (! empty($booking->trip_itineraries)) {
            $snapshot = $booking->trip_itineraries[0];
            $message->line('Your saved itinerary and route/weather explanations are attached. Current item availability and booking status apply.')
                ->attachData(Pdf::loadView('trip-planner.document', ['itinerary' => $snapshot, 'reference' => 'Booking '.$booking->booking_code, 'referenceLabel' => 'Booking reference', 'referenceCode' => $booking->booking_code, 'booking' => $booking->loadMissing('items')])->setPaper('a4')->output(),
                    'sunnytrips-itinerary.pdf', ['mime' => 'application/pdf']);
        }

        return $message;
    }

    protected function message(): string
    {
        return "Payment confirmed for booking {$this->booking->booking_code} — reservation is now confirmed.";
    }
}
