<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\BookingApproved;
use App\Notifications\BookingCancellationApproved;
use App\Notifications\BookingCancellationDenied;
use App\Notifications\BookingCancellationRequested;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingExpired;
use App\Notifications\BookingPaymentDue;
use App\Services\TripJourneyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

#[Signature('mail:force {type : One of the types listed below} {booking_code : Booking whose details fill the email} {--email= : Address that receives the email (required)} {--allow-production : Allow sending on a production server after confirming}')]
#[Description('Send one booking or trip email to an address right now, to demo or check email delivery')]
class ForceMailCommand extends Command
{
    /**
     * Email types and what each one sends.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'reserved' => 'Booking approved / reserved notice',
        'payment-due' => 'Payment reminder before the initial deadline',
        'balance-due' => 'Remaining 50% balance reminder',
        'payment-overdue' => 'Payment deadline passed',
        'cancellation-requested' => 'Admin notice of a cancellation request',
        'cancellation-approved' => 'Cancellation approved (rebook or refund wording)',
        'cancellation-denied' => 'Cancellation denied',
        'cancelled' => 'Booking cancelled',
        'expired' => 'Booking expired (rebook)',
        'pre-trip' => 'Trip date reminder before arrival',
        'departure' => 'Reminder before heading home',
    ];

    /**
     * Execute the console command.
     */
    public function handle(TripJourneyService $journey): int
    {
        $type = (string) $this->argument('type');
        $email = trim((string) $this->option('email'));

        if (! array_key_exists($type, self::TYPES)) {
            $this->error("Unknown type \"{$type}\".");
            $this->table(['Type', 'Sends'], collect(self::TYPES)->map(fn (string $label, string $key) => [$key, $label])->values()->all());

            return self::FAILURE;
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Pass the address to send to with --email=you@example.com. Nothing was sent.');

            return self::FAILURE;
        }
        if (app()->isProduction()) {
            if (! $this->option('allow-production')) {
                $this->error('Refusing to send on a production server. Add --allow-production to confirm you want to send this email.');

                return self::FAILURE;
            }
            if (! $this->confirm("Send a real \"{$type}\" email to {$email} from production?")) {
                $this->warn('Cancelled. Nothing was sent.');

                return self::FAILURE;
            }
        }

        $booking = Booking::query()->with('items')->where('booking_code', $this->argument('booking_code'))->first();
        if (! $booking) {
            $this->error('No booking has the code '.$this->argument('booking_code').'.');

            return self::FAILURE;
        }
        if (($problem = $this->mismatch($type, $booking)) !== null) {
            $this->error($problem.' Nothing was sent.');

            return self::FAILURE;
        }

        try {
            $subject = in_array($type, ['pre-trip', 'departure'], true)
                ? $journey->forceSend($booking, str_replace('-', '_', $type), $email)['subject']
                : $this->sendNotification($this->notificationFor($type, $booking), $email);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage().' Nothing was sent.');

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('The mail provider rejected the email: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent "'.self::TYPES[$type].'" for '.$booking->booking_code.' to '.$email.'.');
        $this->line('Subject: '.$subject);
        $this->line('Mailer: '.config('mail.default').'. "Sent" means the provider accepted it. Check the inbox, the spam folder and the provider dashboard.');

        return self::SUCCESS;
    }

    /**
     * Why the booking cannot fill this email type correctly, if it cannot.
     */
    private function mismatch(string $type, Booking $booking): ?string
    {
        return match ($type) {
            'payment-due' => $booking->payment_deadline ? null : 'This booking has no payment deadline yet; approve it first or choose another booking.',
            'balance-due' => $booking->deposit_secured_at && $booking->balance_due_at ? null : 'This booking has no paid 50% deposit with a balance due date; choose a booking paid by deposit.',
            'payment-overdue' => ($booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline) ? null : 'This booking has no payment deadline to be overdue.',
            default => null,
        };
    }

    private function notificationFor(string $type, Booking $booking): Notification
    {
        return match ($type) {
            'reserved' => new BookingApproved($booking),
            'payment-due' => new BookingPaymentDue($booking, 'initial_due'),
            'balance-due' => new BookingPaymentDue($booking, 'balance_due'),
            'payment-overdue' => new BookingPaymentDue($booking, 'overdue'),
            'cancellation-requested' => new BookingCancellationRequested($booking, includeMail: true),
            'cancellation-approved' => new BookingCancellationApproved($booking),
            'cancellation-denied' => new BookingCancellationDenied($booking),
            'cancelled' => new BookingCancelled($booking),
            'expired' => new BookingExpired($booking),
        };
    }

    /**
     * Deliver on the mail channel only and without the queue: an on-demand
     * address has no database inbox, and the result should show here.
     */
    private function sendNotification(Notification $notification, string $email): string
    {
        $recipient = NotificationFacade::route('mail', $email);
        NotificationFacade::sendNow($recipient, $notification, ['mail']);

        return (string) $notification->toMail($recipient)->subject;
    }
}
