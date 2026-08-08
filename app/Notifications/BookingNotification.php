<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

abstract class BookingNotification extends Notification
{
    public function __construct(protected Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    abstract public function toMail(object $notifiable);

    public function toArray(object $notifiable): array
    {
        $data = [
            'booking_code' => $this->booking->booking_code,
            'booking_url' => route('booking.show', $this->booking->booking_code),
            'status' => $this->booking->status,
            'message' => $this->message(),
        ];

        if ((float) $this->booking->admin_discount_amount > 0 || (float) $this->booking->admin_surcharge_amount > 0) {
            $data['admin_adjustment'] = [
                'discount' => (float) $this->booking->admin_discount_amount,
                'surcharge' => (float) $this->booking->admin_surcharge_amount,
                'reason' => $this->booking->price_adjustment_reason,
            ];
        }

        return $data;
    }

    abstract protected function message(): string;

    /**
     * Deliver a booking notification to the owner (DB + mail) or, for guest
     * bookings, fall back to email via the contact address.
     */
    public static function send(Booking $booking, BookingNotification $notification): void
    {
        if ($booking->user) {
            $booking->user->notify($notification);
            return;
        }

        NotificationFacade::route('mail', $booking->contact_email)->notify($notification);
    }
}
