<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Models\BookingPaymentTransaction;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaymentDue;
use App\Notifications\BookingPaymentReceived;
use App\Services\AdminNotificationService;
use App\Services\BookingExpiryService;
use App\Services\Payment\InstallmentPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBookingPayments extends Command
{
    protected $signature = 'payments:process {--limit=100}';

    protected $description = 'Reconcile installment requests, process deadlines, and deliver durable payment notices';

    public function handle(InstallmentPaymentService $payments, BookingExpiryService $expiry): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        BookingPaymentRequest::whereIn('status', ['creating', 'pending', 'processing'])->where('gateway', 'stripe')
            ->orderBy('updated_at')->limit($limit)->get()->each(function ($request) use ($payments): void {
                try {
                    $payments->check($request);
                } catch (\Throwable $exception) {
                    Log::warning('Installment reconciliation needs retry.', ['request_id' => $request->id, 'exception_type' => $exception::class]);
                } finally {
                    $request->touch();
                }
            });
        Booking::where('payment_plan_version', 2)->where('status', 'approved')
            ->orderBy('id')->chunkById(100, function ($bookings) use ($expiry): void {
                foreach ($bookings as $booking) {
                    $expiry->expireIfDue($booking);
                    $booking->refresh();
                    if ($booking->status !== 'approved') {
                        continue;
                    }
                    $deadline = $booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline;
                    if (! $deadline || $deadline->gt(now()->addDays(2))) {
                        continue;
                    }
                    $kind = $deadline->isPast() ? 'overdue' : ($booking->deposit_secured_at ? 'balance_due' : 'initial_due');
                    DB::table('booking_payment_notices')->insertOrIgnore([
                        'booking_id' => $booking->id, 'dedupe_key' => self::deadlineKey($booking, $kind), 'kind' => $kind,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        $ids = DB::table('booking_payment_notices')->whereNull('sent_at')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id): void {
                    $notice = DB::table('booking_payment_notices')->where('id', $id)->lockForUpdate()->first();
                    if (! $notice || $notice->sent_at) {
                        return;
                    }
                    $booking = Booking::findOrFail($notice->booking_id);
                    if ($notice->booking_payment_transaction_id) {
                        $transaction = BookingPaymentTransaction::findOrFail($notice->booking_payment_transaction_id);
                        BookingNotification::send($booking, new BookingPaymentReceived($booking, $transaction, $notice->kind === 'fully_paid'));
                    } elseif ($booking->status === 'approved' && $notice->kind === self::currentDeadlineKind($booking) && $notice->dedupe_key === self::deadlineKey($booking, $notice->kind)) {
                        BookingNotification::send($booking, new BookingPaymentDue($booking, $notice->kind));
                        if ($notice->kind === 'overdue') {
                            app(AdminNotificationService::class)->send('payment_submitted', $booking);
                        }
                    }
                    DB::table('booking_payment_notices')->where('id', $id)->update(['sent_at' => now(), 'updated_at' => now()]);
                });
            } catch (\Throwable $exception) {
                Log::warning('Payment notification needs retry.', ['notice_id' => $id, 'exception_type' => $exception::class]);
            }
        }
        $this->info('Payment requests, deadlines and pending notices processed.');

        return self::SUCCESS;
    }

    private static function currentDeadlineKind(Booking $booking): string
    {
        $deadline = $booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline;

        return $deadline?->isPast() ? 'overdue' : ($booking->deposit_secured_at ? 'balance_due' : 'initial_due');
    }

    private static function deadlineKey(Booking $booking, string $kind): string
    {
        $deadline = $booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline;

        return $booking->id.'-'.$kind.'-'.hash('sha256', (string) $deadline?->toIso8601String());
    }
}
