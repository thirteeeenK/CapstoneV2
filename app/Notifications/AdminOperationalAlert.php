<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminOperationalAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public readonly string $type, public readonly string $reference, public readonly string $reviewUrl)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $title = match ($this->type) {
            'booking_requested' => 'New booking request',
            'payment_submitted' => 'Payment submitted for review',
            'cancellation_requested' => 'Cancellation request',
            'support_requested' => 'New support inquiry',
            default => 'Operational alert',
        };

        return (new MailMessage)->subject($title.' — '.$this->reference)
            ->greeting('SunnyTrips Admin,')->line($title.': '.$this->reference)
            ->action('Review in admin', $this->reviewUrl);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['type' => $this->type, 'reference' => $this->reference];
    }
}
