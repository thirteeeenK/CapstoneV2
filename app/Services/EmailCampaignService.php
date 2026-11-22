<?php

namespace App\Services;

use App\Jobs\SendCampaignMail;
use App\Mail\PromotionalMail;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\DestinationModel;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailCampaignRun;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin promotional email: who a campaign reaches, when each send starts,
 * the daily sending cap, and the checks every email passes just before it
 * goes out. Only travelers who opted in to marketing email are ever reached.
 */
class EmailCampaignService
{
    /** Run key of a manual or scheduled campaign's single send. */
    public const ONE_OFF_RUN = 'send';

    /** Email jobs released per scheduler tick, spaced to respect the provider's rate limit. */
    private const DISPATCH_BATCH = 120;

    /** Emails started per second (Resend allows two a second on its default plan). */
    private const PER_SECOND = 2;

    public function __construct(private readonly SystemSettingsService $settings) {}

    /**
     * Opted-in, reachable travelers matching a campaign's filters.
     *
     * @param  array{segment?: string, destinations?: array<int, string>, traveler_types?: array<int, string>, inactive_days?: int}  $filters
     * @return Builder<User>
     */
    public function audience(array $filters): Builder
    {
        $segment = $filters['segment'] ?? 'all';
        $destinations = array_values(array_filter($filters['destinations'] ?? []));
        $travelerTypes = array_values(array_filter($filters['traveler_types'] ?? []));
        $inactiveDays = max(1, (int) ($filters['inactive_days'] ?? 90));

        return User::query()
            ->where('trip_marketing_enabled', true)
            ->whereNotNull('email')->where('email', '!=', '')
            ->whereNotIn('id', User::query()->activeBan()->select('id'))
            ->when($destinations !== [], fn (Builder $q) => $q->whereHas('userPreference', fn (Builder $p) => $p->whereIn('destination', $destinations)))
            ->when($travelerTypes !== [], fn (Builder $q) => $q->whereHas('userPreference', fn (Builder $p) => $p->whereIn('traveler_type', $travelerTypes)))
            ->when($segment === 'past_travelers', fn (Builder $q) => $q->whereHas('bookings', fn (Builder $b) => $b->where('status', Booking::STATUS_COMPLETED)))
            ->when($segment === 'never_booked', fn (Builder $q) => $q->whereDoesntHave('bookings'))
            ->when($segment === 'inactive', fn (Builder $q) => $q->whereDoesntHave('bookings', fn (Builder $b) => $b->where('created_at', '>=', now()->subDays($inactiveDays))));
    }

    /**
     * Send a manual campaign right away. Delivery follows the daily cap.
     */
    public function sendNow(EmailCampaign $campaign): ?EmailCampaignRun
    {
        $campaign->forceFill(['status' => EmailCampaign::STATUS_SENDING, 'scheduled_at' => null])->save();
        $run = $this->startRun($campaign, self::ONE_OFF_RUN, $this->linkedContext($campaign));
        $this->dispatchPending();

        return $run;
    }

    /**
     * Stop a campaign: nothing waiting for the cap goes out. Emails already
     * handed to the provider cannot be recalled.
     */
    public function cancel(EmailCampaign $campaign): int
    {
        return DB::transaction(function () use ($campaign): int {
            $campaign->forceFill(['status' => $campaign->isAutomatic() ? EmailCampaign::STATUS_PAUSED : EmailCampaign::STATUS_CANCELLED])->save();
            $stopped = EmailCampaignRecipient::query()->whereIn('email_campaign_run_id', $campaign->runs()->select('id'))
                ->whereIn('status', [EmailCampaignRecipient::STATUS_PENDING, EmailCampaignRecipient::STATUS_QUEUED])
                ->update(['status' => EmailCampaignRecipient::STATUS_CANCELLED, 'status_reason' => 'campaign_stopped', 'updated_at' => now()]);
            $campaign->runs()->where('status', EmailCampaignRun::STATUS_SENDING)->update(['status' => EmailCampaignRun::STATUS_CANCELLED, 'completed_at' => now()]);

            return $stopped;
        });
    }

    /**
     * The scheduler's every-minute pass: start due scheduled campaigns and
     * promotions that went live on their own, release email within today's
     * cap, then close runs that have nothing left to send.
     */
    public function tick(): int
    {
        EmailCampaign::query()->where('send_mode', EmailCampaign::MODE_SCHEDULED)->where('status', EmailCampaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())->get()
            ->each(function (EmailCampaign $campaign): void {
                $campaign->forceFill(['status' => EmailCampaign::STATUS_SENDING])->save();
                $this->startRun($campaign, self::ONE_OFF_RUN, $this->linkedContext($campaign));
            });

        // A promotion scheduled to start later goes live without being saved, so it is found here.
        // redeem_starts_at is timestamptz: bind with an explicit offset so the database session's time zone cannot shift it.
        Promotion::query()->where('is_active', true)->whereNotNull('redeem_starts_at')->where('redeem_starts_at', '<=', now()->toIso8601String())
            ->where('redeem_starts_at', '>=', now()->subDays(2)->toIso8601String())->get()
            ->each(fn (Promotion $promotion) => $this->promotionWentLive($promotion,
                fn (EmailCampaign $campaign) => $promotion->redeem_starts_at->gte($campaign->activated_at)));

        $released = $this->dispatchPending();
        $this->completeFinishedRuns();

        return $released;
    }

    /**
     * Fire automatic "promotion goes live" campaigns for a promotion that has
     * just become active: created or switched on (from the model), or reaching
     * its scheduled start (from the scheduler). Each promotion is announced once.
     *
     * @param  (callable(EmailCampaign): bool)|null  $applies
     */
    public function promotionWentLive(Promotion $promotion, ?callable $applies = null): void
    {
        if ($promotion->status() !== Promotion::STATUS_ACTIVE) {
            return;
        }
        $this->fire(EmailCampaign::TRIGGER_PROMOTION_LIVE, 'promotion:'.$promotion->id, $this->promotionContext($promotion), $applies ?? fn () => true);
    }

    /**
     * Fire automatic "new package" campaigns when a package is published.
     */
    public function packagePublished(Package $package): void
    {
        $this->fire(EmailCampaign::TRIGGER_PACKAGE_PUBLISHED, 'package:'.$package->id, $this->packageContext($package),
            fn (EmailCampaign $campaign) => true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  callable(EmailCampaign): bool  $applies
     */
    private function fire(string $trigger, string $runKey, array $context, callable $applies): void
    {
        try {
            EmailCampaign::query()->where('send_mode', EmailCampaign::MODE_AUTOMATIC)->where('trigger', $trigger)
                ->where('status', EmailCampaign::STATUS_ACTIVE)->whereNotNull('activated_at')->get()
                ->filter($applies)
                ->each(fn (EmailCampaign $campaign) => $this->startRun($campaign, $runKey, $context) && $this->dispatchPending());
        } catch (Throwable $exception) {
            // Announcing a promotion or package must never stop it from being saved.
            Log::error('Automatic email campaign could not start.', ['trigger' => $trigger, 'run' => $runKey, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * Create a send and its recipient list once; a run key already used
     * returns null, so a campaign never reaches the same audience twice.
     *
     * @param  array<string, mixed>  $context
     */
    public function startRun(EmailCampaign $campaign, string $runKey, array $context = []): ?EmailCampaignRun
    {
        return DB::transaction(function () use ($campaign, $runKey, $context): ?EmailCampaignRun {
            $run = EmailCampaignRun::query()->firstOrCreate(
                ['email_campaign_id' => $campaign->id, 'run_key' => $runKey],
                ['context' => $context, 'status' => EmailCampaignRun::STATUS_SENDING, 'started_at' => now()],
            );
            if (! $run->wasRecentlyCreated) {
                return null;
            }
            $count = 0;
            $this->audience($campaign->audienceFilters())->select(['id', 'email'])->chunkById(500, function ($users) use ($run, &$count): void {
                $now = now();
                $count += EmailCampaignRecipient::query()->insertOrIgnore($users->map(fn (User $user) => [
                    'email_campaign_run_id' => $run->id, 'user_id' => $user->id, 'email' => $user->email,
                    'status' => EmailCampaignRecipient::STATUS_PENDING, 'created_at' => $now, 'updated_at' => $now,
                ])->all());
            });
            $run->forceFill(['recipient_count' => $count])->save();

            return $run;
        });
    }

    /**
     * Hand waiting emails to the queue within today's cap (Philippine day),
     * spaced a few per second. Paused or cancelled campaigns release nothing.
     */
    public function dispatchPending(): int
    {
        $remaining = $this->remainingToday();
        if ($remaining <= 0) {
            return 0;
        }
        $ids = EmailCampaignRecipient::query()->where('status', EmailCampaignRecipient::STATUS_PENDING)
            ->whereHas('run', fn (Builder $run) => $run->where('status', EmailCampaignRun::STATUS_SENDING)
                ->whereHas('campaign', fn (Builder $c) => $c->whereIn('status', [EmailCampaign::STATUS_SENDING, EmailCampaign::STATUS_ACTIVE])))
            ->orderBy('id')->limit(min($remaining, self::DISPATCH_BATCH))->pluck('id');
        $released = 0;
        foreach ($ids as $id) {
            $claimed = EmailCampaignRecipient::query()->whereKey($id)->where('status', EmailCampaignRecipient::STATUS_PENDING)
                ->update(['status' => EmailCampaignRecipient::STATUS_QUEUED, 'queued_at' => now(), 'updated_at' => now()]);
            if ($claimed) {
                SendCampaignMail::dispatch($id)->delay(now()->addSeconds(intdiv($released, self::PER_SECOND)))->afterCommit();
                $released++;
            }
        }

        return $released;
    }

    /**
     * Emails that may still be released today under the admin's daily cap.
     */
    public function remainingToday(): int
    {
        $startOfDay = now('Asia/Manila')->startOfDay()->timezone(config('app.timezone'));
        $used = EmailCampaignRecipient::query()->whereNotNull('queued_at')->where('queued_at', '>=', $startOfDay)
            ->whereIn('status', [EmailCampaignRecipient::STATUS_QUEUED, EmailCampaignRecipient::STATUS_SENDING, EmailCampaignRecipient::STATUS_SENT, EmailCampaignRecipient::STATUS_FAILED])
            ->count();

        return max(0, (int) $this->settings->section('marketing')['daily_cap'] - $used);
    }

    private function completeFinishedRuns(): void
    {
        EmailCampaignRun::query()->where('status', EmailCampaignRun::STATUS_SENDING)
            ->whereDoesntHave('recipients', fn (Builder $q) => $q->whereIn('status', [EmailCampaignRecipient::STATUS_PENDING, EmailCampaignRecipient::STATUS_QUEUED, EmailCampaignRecipient::STATUS_SENDING]))
            ->with('campaign')->get()
            ->each(function (EmailCampaignRun $run): void {
                $run->forceFill(['status' => EmailCampaignRun::STATUS_COMPLETED, 'completed_at' => now()])->save();
                if (! $run->campaign->isAutomatic() && $run->campaign->status === EmailCampaign::STATUS_SENDING) {
                    $run->campaign->forceFill(['status' => EmailCampaign::STATUS_SENT])->save();
                }
            });
    }

    /**
     * Why an email should not go out now, or null when it may: the traveler
     * must still be opted in and reachable, not emailed recently, and any
     * promotion or package it announces must still be on offer.
     */
    public function skipReason(EmailCampaignRecipient $recipient): ?string
    {
        $run = $recipient->run()->with('campaign')->first();
        $campaign = $run?->campaign;
        if (! $campaign || ! in_array($campaign->status, [EmailCampaign::STATUS_SENDING, EmailCampaign::STATUS_ACTIVE], true)) {
            return 'campaign_stopped';
        }
        $user = User::query()->find($recipient->user_id);
        if (! $user || blank($user->email)) {
            return 'account_removed';
        }
        if (! $user->trip_marketing_enabled) {
            return 'unsubscribed';
        }
        if ($user->isBanned()) {
            return 'account_restricted';
        }
        $context = $run->context ?? [];
        if (! empty($context['promotion_id']) && Promotion::query()->find($context['promotion_id'])?->status() !== Promotion::STATUS_ACTIVE) {
            return 'promotion_ended';
        }
        if (! empty($context['package_id']) && ! Package::query()->whereKey($context['package_id'])->where('is_active', true)->exists()) {
            return 'package_unavailable';
        }
        $days = (int) $this->settings->section('marketing')['frequency_days'];
        if ($days > 0 && $this->emailedRecently($user, $recipient, now()->subDays($days))) {
            return 'emailed_recently';
        }

        return null;
    }

    private function emailedRecently(User $user, EmailCampaignRecipient $recipient, Carbon $since): bool
    {
        return EmailCampaignRecipient::query()->where('user_id', $user->id)->whereKeyNot($recipient->id)
            ->where('status', EmailCampaignRecipient::STATUS_SENT)->where('sent_at', '>=', $since)->exists()
            || DB::table('trip_journey_messages')->where('user_id', $user->id)->whereIn('kind', ['continue', 'repeat'])
                ->where('status', 'sent')->where('sent_at', '>=', $since)->exists();
    }

    /**
     * The email a traveler receives (or the admin previews) with the
     * placeholders filled in. Content is plain text, escaped when rendered.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function payload(EmailCampaign $campaign, array $context, ?User $user, ?string $unsubscribeUrl = null): array
    {
        $values = [
            '{first_name}' => $user ? Str::of($user->name)->trim()->explode(' ')->first() : 'there',
            '{promotion}' => $context['promotion'] ?? 'our latest Lucky promotion',
            '{discount}' => $context['discount'] ?? 'a surprise discount',
            '{package}' => $context['package'] ?? 'our newest tour package',
            '{destination}' => $context['destination'] ?? 'the islands',
            '{price}' => $context['price'] ?? '',
        ];
        $fill = fn (?string $text) => $text === null ? null : trim(strtr($text, $values));
        $paragraphs = collect(preg_split('/\R{2,}/', (string) $fill($campaign->body)))->map(fn ($p) => trim($p))->filter()->values()->all();

        return [
            'subject' => $fill($campaign->subject),
            'preheader' => $fill($campaign->preheader),
            'headline' => $fill($campaign->headline),
            'greeting' => 'Hi '.$values['{first_name}'].',',
            'paragraphs' => $paragraphs,
            'image_url' => $campaign->image_path ? Storage::disk('public')->url($campaign->image_path) : null,
            'cta_label' => $fill($campaign->cta_label) ?: 'Explore now',
            'cta_url' => $this->ctaUrl($campaign, $context),
            'unsubscribe_url' => $unsubscribeUrl,
            'business' => $this->settings->section('business')['display_name'] ?: config('app.name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function ctaUrl(EmailCampaign $campaign, array $context = []): string
    {
        if ($campaign->trigger === EmailCampaign::TRIGGER_PACKAGE_PUBLISHED && ! empty($context['package'])) {
            return route('packages.index', ['search' => $context['package']]);
        }

        return match ($campaign->cta_target) {
            'destination' => $campaign->cta_target_id ? route('destinations.show', $campaign->cta_target_id) : route('destinations.index'),
            'package' => ($name = Package::query()->whereKey($campaign->cta_target_id)->value('name')) ? route('packages.index', ['search' => $name]) : route('packages.index'),
            'packages' => route('packages.index'),
            'trip_planner' => route('trip-planner.index'),
            'custom' => url('/'.ltrim((string) $campaign->cta_path, '/')),
            default => route('lucky.index'),
        };
    }

    /**
     * Placeholder values for the promotion a campaign links to, if any.
     *
     * @return array<string, mixed>
     */
    public function linkedContext(EmailCampaign $campaign): array
    {
        $promotion = $campaign->promotion;

        return $promotion ? $this->promotionContext($promotion) : [];
    }

    /**
     * Sample values so an automatic campaign's preview reads like a real email.
     *
     * @return array<string, mixed>
     */
    public function previewContext(EmailCampaign $campaign): array
    {
        return match ($campaign->trigger) {
            EmailCampaign::TRIGGER_PROMOTION_LIVE => $this->linkedContext($campaign) ?: ['promotion' => 'Summer Splash', 'discount' => '15% off'],
            EmailCampaign::TRIGGER_PACKAGE_PUBLISHED => ($package = Package::query()->with('destination')->where('is_active', true)->latest('id')->first())
                ? $this->packageContext($package)
                : ['package' => 'Island Hopping Escape', 'destination' => 'El Nido', 'price' => '₱8,500.00'],
            default => $this->linkedContext($campaign),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionContext(Promotion $promotion): array
    {
        return ['promotion_id' => $promotion->id, 'promotion' => $promotion->name, 'discount' => $promotion->discountLabel()];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageContext(Package $package): array
    {
        $destination = $package->destination ?? DestinationModel::query()->find($package->destination_id);

        return ['package_id' => $package->id, 'package' => $package->name, 'destination' => $destination?->name ?? 'the islands',
            'price' => '₱'.number_format((float) $package->price, 2)];
    }

    /**
     * Signed one-click unsubscribe link; it never expires, as mail clients require.
     */
    public function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('marketing.unsubscribe', ['user' => $user->id]);
    }

    /**
     * Send the campaign to the admin's own address now, marked as a test.
     */
    public function sendTest(EmailCampaign $campaign, AdminModel $admin): void
    {
        $payload = $this->payload($campaign, $this->previewContext($campaign), null);
        $payload['subject'] = '[Test] '.$payload['subject'];
        $payload['greeting'] = 'Hi '.(Str::of($admin->name)->trim()->explode(' ')->first() ?: 'there').',';

        Mail::to($admin->email)->send(new PromotionalMail($payload, 'campaign-test-'.$campaign->id.'-'.Str::uuid()));
    }
}
