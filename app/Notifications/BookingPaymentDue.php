<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingPaymentDue extends BookingNotification
{
    use Queueable;

    public function __construct(Booking $booking, public string $kind)
    {
        parent::__construct($booking);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->booking->deposit_secured_at ? $this->booking->balance_due_at : $this->booking->payment_deadline;

        return (new MailMessage)->subject('Payment deadline — '.$this->booking->booking_code)
            ->greeting('Hi '.$this->booking->contact_name.',')->line($this->message())
            ->line('Deadline: '.$deadline?->timezone('Asia/Manila')->format('M j, Y g:i A').' (Philippine time).')
            ->action('Review your booking', route('booking.show', $this->booking->booking_code));
    }

    protected function message(): string
    {
        if ($this->kind === 'extended') {
            return 'Your booking payment deadline has been extended. The new agreed deadline is shown below.';
        }

        return $this->kind === 'overdue'
            ? 'Your payment deadline has passed. Contact support to review your reservation. Any verified payment remains recorded.'
            : ($this->kind === 'balance_due' ? 'Your remaining booking balance is due soon.' : (($this->booking->payment_policy['deposit_eligible'] ?? false)
                ? 'Pay in full or complete your 50% reservation deposit before the initial payment deadline.'
                : 'Full payment is required before the initial payment deadline.'));
    }
}
