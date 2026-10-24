<?php

namespace App\Jobs;

use App\Mail\TripJourneyMail;
use App\Services\TripJourneyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTripJourneyMail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 4;

    public int $timeout = 60;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $messageId) {}

    /**
     * Execute the job.
     */
    public function handle(TripJourneyService $journey): void
    {
        $lock = Cache::lock('trip-journey:'.$this->messageId, 70);
        if (! $lock->get()) {
            $this->release(10);

            return;
        }
        try {
            $message = DB::table('trip_journey_messages')->find($this->messageId);
            if (! $message || ! in_array($message->status, ['pending', 'sending'], true)) {
                return;
            }
            $decision = $journey->deliveryDecision($message);
            if (! $decision['eligible']) {
                DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'suppressed', 'status_reason' => $decision['reason'], 'updated_at' => now()]);

                return;
            }
            $resend = config('mail.mailers.'.config('mail.default').'.transport') === 'resend';
            if ($message->status === 'sending' && ! $resend) {
                DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'uncertain', 'status_reason' => 'interrupted_send', 'updated_at' => now()]);

                return;
            }
            DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'sending', 'recipient_email' => $decision['recipient'], 'attempted_at' => now(), 'updated_at' => now()]);
            $attempted = true;
            $payload = json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR);
            Mail::to($decision['recipient'])->send(new TripJourneyMail($payload, $message->dedupe_key));
            DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'sent', 'status_reason' => 'provider_accepted', 'sent_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $exception) {
            if (isset($message) && $message) {
                // Without provider idempotency, a failure after the send began may still have delivered the email.
                $uncertain = ($attempted ?? false) && ! ($resend ?? false);
                DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => $uncertain ? 'uncertain' : 'pending', 'status_reason' => $uncertain ? 'send_failed_unconfirmed' : 'retrying', 'updated_at' => now()]);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('trip_journey_messages')->where('id', $this->messageId)->whereIn('status', ['pending', 'sending'])->update(['status' => 'failed', 'status_reason' => 'retries_exhausted', 'updated_at' => now()]);
    }
}
