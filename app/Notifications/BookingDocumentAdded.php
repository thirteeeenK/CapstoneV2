<?php

namespace App\Notifications;

use App\Models\BookingAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingDocumentAdded extends BookingNotification
{
    use Queueable;

    public function __construct(protected BookingAttachment $attachment)
    {
        parent::__construct($attachment->booking);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $attachment = $this->attachment;

        return (new MailMessage)
            ->subject("New Travel Document — {$booking->booking_code}")
            ->greeting("Hi {$booking->contact_name},")
            ->line("Our team added a new travel document to your booking **{$booking->booking_code}**: **{$attachment->label}**.")
            ->action('View Travel Documents', route('booking.show', $booking->booking_code))
            ->line('Thank you for traveling with SunnyTrips!');
    }

    protected function message(): string
    {
        return "New travel document ({$this->attachment->label}) added to booking {$this->booking->booking_code}.";
    }
}
