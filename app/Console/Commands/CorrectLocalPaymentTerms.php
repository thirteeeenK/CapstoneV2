<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Models\QrphPaymentAttempt;
use App\Services\AdminAuditService;
use App\Services\Payment\BookingPaymentPolicy;
use App\Services\Payment\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Signature('payments:correct-local {booking* : Explicit local booking IDs} {--apply : Apply the previewed correction} {--initial-deadline= : Explicit replacement deadline in Philippine time}')]
#[Description('Preview or correct unpaid local booking terms without relabeling payment history')]
class CorrectLocalPaymentTerms extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BookingPaymentPolicy $policy, PaymentService $payments): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This correction is restricted to local/testing environments.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($this->argument('booking') as $id) {
            if (! ctype_digit((string) $id)) {
                $this->error('Use explicit numeric booking IDs.');

                return self::FAILURE;
            }
            try {
                $payments->withBookingLock((int) $id, function () use ($id, $policy): void {
                    DB::transaction(function () use ($id, $policy): void {
                        $booking = Booking::query()->lockForUpdate()->findOrFail($id);
                        if ($booking->status !== Booking::STATUS_APPROVED || $booking->payment_status !== Booking::PAYMENT_UNPAID
                            || $booking->payments()->exists() || BookingPaymentRequest::where('booking_id', $booking->id)->exists() || $booking->paymentTransactions()->exists()
                            || QrphPaymentAttempt::where('booking_id', $booking->id)->exists()
                            || $booking->gateway_reference || $booking->payment_reference || $booking->payment_url || $booking->paid_at || $booking->deposit_secured_at) {
                            throw new \RuntimeException('Blocked: booking is not unpaid/approved or has payment history/provider references.');
                        }
                        $deadline = $this->option('initial-deadline') ?: $booking->payment_deadline?->copy()->toIso8601String();
                        if (! $deadline) {
                            throw new \RuntimeException('Specify --initial-deadline; this booking has no existing initial deadline.');
                        }
                        $before = $booking->only(['payment_environment', 'payment_policy', 'payment_deadline', 'balance_due_at', 'payment_plan_version', 'deposit_required_cents']);
                        $terms = $policy->approvalData($booking, $deadline, null, $booking->approved_at);
                        $this->table(['Booking', 'Mode before → after', 'Initial due', 'Balance due', 'Deposit', 'Rule'], [[
                            $booking->id, ($booking->payment_environment ?? 'legacy').' → '.$terms['payment_environment'],
                            $terms['payment_deadline']->copy()->timezone('Asia/Manila')->toDateTimeString(),
                            isset($terms['balance_due_at']) ? $terms['balance_due_at']->copy()->timezone('Asia/Manila')->toDateTimeString() : 'Full payment only',
                            ($terms['payment_policy']['deposit_eligible'] ?? false) ? '50%' : 'Unavailable',
                            $terms['payment_policy']['balance_rule'] ?? 'legacy',
                        ]]);
                        if ($this->option('apply')) {
                            $sameTerms = collect($terms)->every(function ($value, $field) use ($booking): bool {
                                return $value instanceof \DateTimeInterface
                                    ? $booking->{$field}?->equalTo($value) === true
                                    : $booking->{$field} == $value;
                            });
                            if ($sameTerms) {
                                $this->info('Booking '.$booking->id.' already has these terms.');

                                return;
                            }
                            $booking->forceFill($terms)->save();
                            if ($booking->wasChanged()) {
                                AdminAuditService::log($booking, $before);
                                $booking->history()->create(['from_status' => $booking->status, 'to_status' => $booking->status,
                                    'note' => 'Local payment terms corrected; no payment history changed. Prior terms retained in audit log.']);
                            }
                            $this->info('Applied safe local correction for booking '.$booking->id.'.');
                        }
                    });
                });
            } catch (ValidationException $exception) {
                $failed = true;
                $this->error($id.': '.collect($exception->errors())->flatten()->implode(' '));
            } catch (\RuntimeException $exception) {
                $failed = true;
                $this->error($id.': '.$exception->getMessage());
            }
        }
        if (! $this->option('apply')) {
            $this->info('Preview only. Review these terms before repeating with --apply.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
