<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewGuestAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Booking $booking,
        protected string $temporaryPassword,
        protected ?string $resetUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Welcome to SunnyTrips — Your Account & Booking Details')
            ->greeting("Hi {$this->booking->contact_name},")
            ->line("An administrator at SunnyTrips has created a reservation on your behalf for booking **{$this->booking->booking_code}**.")
            ->line('An account has been automatically created for you so you can easily view your booking, manage your trip, and download vouchers.')
            ->line('**Your Login Credentials:**')
            ->line("- Email: **{$notifiable->email}**")
            ->line("- Temporary Password: **{$this->temporaryPassword}**");

        if ($this->resetUrl) {
            $mail->action('Set Your Own Password & Log In', $this->resetUrl)
                ->line('Alternatively, you can log in directly at '.route('login').' using your temporary password and update it anytime in your Profile settings.');
        } else {
            $mail->action('Log in to SunnyTrips', route('login'))
                ->line('We recommend updating your password once you log in.');
        }

        return $mail->line('Thank you for choosing SunnyTrips for your journey!');
    }
}
