<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountModerationNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $level,
        public ?string $reason = null,
        public ?string $expiresAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = $this->reason ?: 'Violation of our community guidelines.';

        return match ($this->level) {
            User::BAN_LEVEL_WARNING => $this->warningMail($notifiable, $reason),
            User::BAN_LEVEL_TEMPORARY => $this->temporaryMail($notifiable, $reason),
            User::BAN_LEVEL_PERMANENT => $this->permanentMail($notifiable, $reason),
            default => $this->restoredMail($notifiable),
        };
    }

    private function warningMail(object $notifiable, string $reason): MailMessage
    {
        return (new MailMessage)
            ->subject('Warning Notice — SunnyTrips Account')
            ->greeting("Hi {$notifiable->name},")
            ->line('Our moderation team has issued a **warning** on your SunnyTrips account.')
            ->line("Reason: **{$reason}**")
            ->line('Your access is not restricted, but further violations may lead to a temporary or permanent suspension.')
            ->action('Visit SunnyTrips', url('/'))
            ->line('Please review our community guidelines. — SunnyTrips Team');
    }

    private function temporaryMail(object $notifiable, string $reason): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Suspended — SunnyTrips')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your SunnyTrips account has been **temporarily suspended**.')
            ->line("Reason: **{$reason}**")
            ->line("Access is restored on **{$this->expiresAt}**.")
            ->line('If you believe this was a mistake, you may contact our support team.')
            ->line('— SunnyTrips Team');
    }

    private function permanentMail(object $notifiable, string $reason): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Permanently Banned — SunnyTrips')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your SunnyTrips account has been **permanently banned**.')
            ->line("Reason: **{$reason}**")
            ->line('This decision is final and access will not be restored.')
            ->line('— SunnyTrips Team');
    }

    private function restoredMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Restored — SunnyTrips')
            ->greeting("Hi {$notifiable->name},")
            ->line('Good news — your SunnyTrips account has been **restored** to normal access.')
            ->line('You can log in and use all features again, including the AI Chatbot.')
            ->action('Log In', url('/login'))
            ->line('Welcome back! — SunnyTrips Team');
    }
}
