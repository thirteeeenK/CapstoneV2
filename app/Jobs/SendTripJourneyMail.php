<?php

namespace App\Jobs;

use App\Mail\TripJourneyMail;
use App\Models\User;
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
            if (! $journey->deliveryEligible($message)) {
                DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'suppressed', 'updated_at' => now()]);

                return;
            }
            $resend = config('mail.mailers.'.config('mail.default').'.transport') === 'resend';
            if ($message->status === 'sending' && ! $resend) {
                DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'uncertain', 'updated_at' => now()]);

                return;
            }
            DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'sending', 'attempted_at' => now(), 'updated_at' => now()]);
            $payload = json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR);
            Mail::to(User::query()->findOrFail($message->user_id)->email)->send(new TripJourneyMail($payload, $message->dedupe_key));
            DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $exception) {
            if (isset($message) && $message) {
                DB::table('trip_journey_messages')->where('id', $message->id)->update(['status' => ($resend ?? false) ? 'pending' : 'uncertain', 'updated_at' => now()]);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('trip_journey_messages')->where('id', $this->messageId)->whereIn('status', ['pending', 'sending'])->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
