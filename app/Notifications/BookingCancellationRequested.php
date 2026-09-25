<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancellationRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $reason = $booking->cancellation_request_reason ?: 'No reason provided.';

        return (new MailMessage)
            ->subject("Cancellation Request — {$booking->booking_code}")
            ->greeting('SunnyTrips Admin,')
            ->line("A cancellation has been requested for booking **{$booking->booking_code}**.")
            ->line("Guest: {$booking->contact_name} ({$booking->contact_email})")
            ->line("Reason: {$reason}")
            ->action('Review Request', route('admin.bookings.show', $booking->id))
            ->line('Please approve or deny the request from the booking detail page.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_code' => $this->booking->booking_code,
            'booking_id' => $this->booking->id,
            'booking_url' => route('admin.bookings.show', $this->booking->id),
            'status' => $this->booking->status,
            'message' => "Cancellation requested for {$this->booking->booking_code}: ".($this->booking->cancellation_request_reason ?: 'No reason provided.'),
        ];
    }
}
