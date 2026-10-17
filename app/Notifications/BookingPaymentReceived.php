<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use App\Models\BookingPaymentTransaction;
use App\Services\Payment\BookingPaymentAccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingPaymentReceived extends BookingNotification
{
    use Queueable;

    public function __construct(Booking $booking, public BookingPaymentTransaction $transaction, public bool $fullyPaid = false)
    {
        parent::__construct($booking);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $summary = app(BookingPaymentAccountingService::class)->summary($this->booking);
        $mail = (new MailMessage)->subject(($this->fullyPaid ? 'Booking fully paid' : 'Payment received').' — '.$this->booking->booking_code)
            ->greeting('Hi '.$this->booking->contact_name.',')
            ->line($this->message())
            ->line('This payment: ₱'.number_format($this->transaction->amount / 100, 2))
            ->line('Reference: '.$this->transaction->source_reference)
            ->line('Verified total: ₱'.number_format($summary['verified_cents'] / 100, 2))
            ->line('Remaining balance: ₱'.number_format($summary['remaining_cents'] / 100, 2));
        if ($this->booking->balance_due_at && $summary['remaining_cents'] > 0) {
            $mail->line('Balance due: '.$this->booking->balance_due_at->timezone('Asia/Manila')->format('M j, Y g:i A').' (Philippine time).');
        }

        return $mail->action('View booking and payment history', route('booking.show', $this->booking->booking_code));
    }

    protected function message(): string
    {
        if (! $this->transaction->allocated) {
            return 'Your payment was received and needs support review. Please do not pay again.';
        }

        return $this->fullyPaid ? 'Your booking is fully paid and confirmed.' : ($this->booking->deposit_secured_at ? 'Your reservation deposit is verified. Your remaining balance is still due.' : 'Your partial payment is verified. The reservation deposit is not yet fully covered.');
    }
}
