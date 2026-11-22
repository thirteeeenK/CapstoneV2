<?php

namespace App\Jobs;

use App\Mail\PromotionalMail;
use App\Models\EmailCampaignRecipient;
use App\Models\User;
use App\Services\EmailCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Deliver one campaign email after checking, at the moment of sending, that
 * the traveler can still receive it. Retries reuse the provider idempotency
 * key, so a retry after an unclear failure never sends a second copy.
 */
class SendCampaignMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $recipientId) {}

    public function handle(EmailCampaignService $campaigns): void
    {
        $claimed = EmailCampaignRecipient::query()->whereKey($this->recipientId)
            ->whereIn('status', [EmailCampaignRecipient::STATUS_QUEUED, EmailCampaignRecipient::STATUS_SENDING])
            ->update(['status' => EmailCampaignRecipient::STATUS_SENDING, 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $recipient = EmailCampaignRecipient::query()->with('run.campaign')->findOrFail($this->recipientId);
        if (($reason = $campaigns->skipReason($recipient)) !== null) {
            $recipient->forceFill(['status' => EmailCampaignRecipient::STATUS_SKIPPED, 'status_reason' => $reason])->save();

            return;
        }
        $user = User::query()->findOrFail($recipient->user_id);

        try {
            $payload = $campaigns->payload($recipient->run->campaign, $recipient->run->context ?? [], $user, $campaigns->unsubscribeUrl($user));
            Mail::to($user->email)->send(new PromotionalMail($payload, 'campaign-recipient-'.$recipient->id));
        } catch (Throwable $exception) {
            $recipient->forceFill(['status' => EmailCampaignRecipient::STATUS_QUEUED, 'status_reason' => 'retrying'])->save();
            throw $exception;
        }
        $recipient->forceFill(['status' => EmailCampaignRecipient::STATUS_SENT, 'status_reason' => 'provider_accepted', 'email' => $user->email, 'sent_at' => now()])->save();
    }

    public function failed(?Throwable $exception): void
    {
        EmailCampaignRecipient::query()->whereKey($this->recipientId)
            ->whereIn('status', [EmailCampaignRecipient::STATUS_QUEUED, EmailCampaignRecipient::STATUS_SENDING])
            ->update(['status' => EmailCampaignRecipient::STATUS_FAILED, 'status_reason' => 'retries_exhausted', 'updated_at' => now()]);
    }
}
