<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmailCampaignRequest;
use App\Models\DestinationModel;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\Package;
use App\Models\Promotion;
use App\Services\AdminAuditService;
use App\Services\EmailCampaignService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * Admin promotional email campaigns: write, preview and test a campaign,
 * then send it now, schedule it, or leave it on to send automatically.
 */
class AdminEmailCampaignController extends Controller
{
    /** Status groups behind the list page's tabs. */
    private const TABS = [
        'drafts' => [EmailCampaign::STATUS_DRAFT],
        'scheduled' => [EmailCampaign::STATUS_SCHEDULED, EmailCampaign::STATUS_SENDING],
        'sent' => [EmailCampaign::STATUS_SENT, EmailCampaign::STATUS_CANCELLED],
        'automatic' => [EmailCampaign::STATUS_ACTIVE, EmailCampaign::STATUS_PAUSED],
    ];

    public function __construct(private readonly EmailCampaignService $campaigns) {}

    public function index(Request $request, SystemSettingsService $settings): View
    {
        $search = $request->query('search');
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : '';

        $campaigns = EmailCampaign::query()
            ->withCount([
                'recipients as sent_count' => fn ($q) => $q->where('email_campaign_recipients.status', EmailCampaignRecipient::STATUS_SENT),
                'recipients as waiting_count' => fn ($q) => $q->whereIn('email_campaign_recipients.status', [EmailCampaignRecipient::STATUS_PENDING, EmailCampaignRecipient::STATUS_QUEUED, EmailCampaignRecipient::STATUS_SENDING]),
            ])
            ->when($search, fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'ilike', "%{$search}%")->orWhere('subject', 'ilike', "%{$search}%")))
            ->when($tab, fn ($q) => $q->whereIn('status', self::TABS[$tab]))
            ->latest('id')->paginate(15)->withQueryString();

        if ($request->ajax()) {
            return view('admin.email-campaigns._results', compact('campaigns'));
        }

        return view('admin.email-campaigns.index', compact('campaigns', 'search', 'tab') + [
            'limits' => $settings->section('marketing'), 'limitsRevision' => $settings->revision('marketing'),
            'stats' => [
                'subscribers' => $this->campaigns->audience([])->count(),
                'remaining' => $this->campaigns->remainingToday(),
                'sent_30d' => EmailCampaignRecipient::query()->where('status', EmailCampaignRecipient::STATUS_SENT)->where('sent_at', '>=', now()->subDays(30))->count(),
                'automatic' => EmailCampaign::query()->where('status', EmailCampaign::STATUS_ACTIVE)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $campaign = new EmailCampaign(['send_mode' => EmailCampaign::MODE_MANUAL, 'cta_target' => 'lucky', 'audience' => ['segment' => 'all']]);

        return view('admin.email-campaigns.create', ['campaign' => $campaign] + $this->formData());
    }

    public function store(StoreEmailCampaignRequest $request): RedirectResponse
    {
        $campaign = new EmailCampaign($request->campaignAttributes() + [
            'created_by_admin_id' => auth('admin')->id(), 'updated_by_admin_id' => auth('admin')->id(),
        ]);
        $this->applyImage($request, $campaign);
        $this->applyIntent($campaign, $request->validated('intent'));
        $campaign->save();
        AdminAuditService::log($campaign);

        return redirect()->route('admin.email-campaigns.show', $campaign)->with('success', $this->savedMessage($campaign));
    }

    public function show(EmailCampaign $campaign): View
    {
        $campaign->load(['promotion', 'runs' => fn ($q) => $q->latest('id')->withCount([
            'recipients as sent_count' => fn ($r) => $r->where('status', EmailCampaignRecipient::STATUS_SENT),
            'recipients as waiting_count' => fn ($r) => $r->whereIn('status', [EmailCampaignRecipient::STATUS_PENDING, EmailCampaignRecipient::STATUS_QUEUED, EmailCampaignRecipient::STATUS_SENDING]),
            'recipients as skipped_count' => fn ($r) => $r->where('status', EmailCampaignRecipient::STATUS_SKIPPED),
            'recipients as failed_count' => fn ($r) => $r->where('status', EmailCampaignRecipient::STATUS_FAILED),
            'recipients as cancelled_count' => fn ($r) => $r->where('status', EmailCampaignRecipient::STATUS_CANCELLED),
        ])]);
        $skipReasons = $campaign->recipients()->where('email_campaign_recipients.status', EmailCampaignRecipient::STATUS_SKIPPED)
            ->selectRaw('email_campaign_recipients.status_reason as reason, count(*) as total')->groupBy('email_campaign_recipients.status_reason')
            ->pluck('total', 'reason');

        return view('admin.email-campaigns.show', [
            'campaign' => $campaign,
            'audienceCount' => $this->campaigns->audience($campaign->audienceFilters())->count(),
            'remainingToday' => $this->campaigns->remainingToday(),
            'skipReasons' => $skipReasons,
            'previewHtml' => $this->renderPreview($campaign),
        ]);
    }

    public function edit(EmailCampaign $campaign): View|RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('admin.email-campaigns.show', $campaign)->with('error', 'This campaign has already started sending, so it can no longer be edited.');
        }

        return view('admin.email-campaigns.edit', ['campaign' => $campaign] + $this->formData());
    }

    public function update(StoreEmailCampaignRequest $request, EmailCampaign $campaign): RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('admin.email-campaigns.show', $campaign)->with('error', 'This campaign has already started sending, so it can no longer be edited.');
        }
        $oldValues = $campaign->getOriginal();
        $campaign->fill($request->campaignAttributes() + ['updated_by_admin_id' => auth('admin')->id()]);
        $this->applyImage($request, $campaign);
        $this->applyIntent($campaign, $request->validated('intent'));
        $campaign->save();
        AdminAuditService::log($campaign, $oldValues);

        return redirect()->route('admin.email-campaigns.show', $campaign)->with('success', $this->savedMessage($campaign));
    }

    /**
     * Render the email from the form as it stands, unsaved, for the live preview.
     */
    public function preview(Request $request): Response
    {
        $campaign = new EmailCampaign([
            'subject' => (string) $request->input('subject', ''), 'preheader' => (string) $request->input('preheader', ''),
            'headline' => (string) $request->input('headline', ''), 'body' => (string) $request->input('body', ''),
            'cta_label' => (string) $request->input('cta_label', ''),
            'cta_target' => array_key_exists((string) $request->input('cta_target'), EmailCampaign::CTA_TARGETS) ? $request->input('cta_target') : 'lucky',
            'cta_target_id' => $request->integer('cta_target_id') ?: null, 'cta_path' => (string) $request->input('cta_path', ''),
            'send_mode' => in_array($request->input('send_mode'), EmailCampaign::MODES, true) ? $request->input('send_mode') : EmailCampaign::MODE_MANUAL,
            'trigger' => array_key_exists((string) $request->input('trigger'), EmailCampaign::TRIGGERS) ? $request->input('trigger') : null,
            'promotion_id' => $request->integer('promotion_id') ?: null,
        ]);
        if ($request->filled('campaign_id') && ($saved = EmailCampaign::query()->find($request->integer('campaign_id')))) {
            $campaign->image_path = $request->boolean('remove_image') ? null : $saved->image_path;
        }

        return response($this->renderPreview($campaign));
    }

    /**
     * How many opted-in travelers the form's audience filters reach right now.
     */
    public function audienceCount(Request $request): JsonResponse
    {
        $filters = (array) $request->input('audience', []);

        return response()->json(['count' => $this->campaigns->audience([
            'segment' => array_key_exists((string) ($filters['segment'] ?? ''), EmailCampaign::SEGMENTS) ? $filters['segment'] : 'all',
            'destinations' => array_values(array_filter((array) ($filters['destinations'] ?? []), 'is_string')),
            'traveler_types' => array_values(array_filter((array) ($filters['traveler_types'] ?? []), 'is_string')),
            'inactive_days' => (int) ($filters['inactive_days'] ?? 90),
        ])->count()]);
    }

    public function sendTest(EmailCampaign $campaign): RedirectResponse
    {
        $admin = auth('admin')->user();
        try {
            $this->campaigns->sendTest($campaign, $admin);
        } catch (Throwable $exception) {
            Log::warning('Test campaign email failed.', ['campaign' => $campaign->id, 'error' => $exception->getMessage()]);

            return back()->with('error', 'The test email could not be sent. Check the mail settings and try again.');
        }

        return back()->with('success', 'Test email sent to '.$admin->email.'.');
    }

    /**
     * Start a manual or scheduled campaign now. Delivery follows the daily cap.
     */
    public function send(EmailCampaign $campaign): RedirectResponse
    {
        if ($campaign->isAutomatic() || ! in_array($campaign->status, [EmailCampaign::STATUS_DRAFT, EmailCampaign::STATUS_SCHEDULED], true)) {
            return back()->with('error', 'Only a draft or scheduled campaign can be sent now.');
        }
        $run = $this->campaigns->sendNow($campaign);
        AdminAuditService::recordAction($campaign, 'email_campaign_sent', [], ['recipients' => $run?->recipient_count ?? 0], auth('admin')->user());
        $count = $run?->recipient_count ?? 0;

        return redirect()->route('admin.email-campaigns.show', $campaign)->with('success', $count === 0
            ? 'Nothing to send: no opted-in traveler matches this audience.'
            : 'Sending to '.number_format($count).' traveler'.($count === 1 ? '' : 's').'. Anything over today’s cap goes out tomorrow.');
    }

    public function cancel(EmailCampaign $campaign): RedirectResponse
    {
        if (! in_array($campaign->status, [EmailCampaign::STATUS_SCHEDULED, EmailCampaign::STATUS_SENDING, EmailCampaign::STATUS_ACTIVE], true)) {
            return back()->with('error', 'This campaign is not sending.');
        }
        $stopped = $this->campaigns->cancel($campaign);
        AdminAuditService::recordAction($campaign, 'email_campaign_cancelled', [], ['stopped' => $stopped], auth('admin')->user());

        return back()->with('success', 'Campaign stopped. '.number_format($stopped).' email(s) that had not gone out were cancelled.');
    }

    /**
     * Switch an automatic campaign on or off. Switching on only announces
     * promotions and packages that go live from now on.
     */
    public function toggle(EmailCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->isAutomatic(), 422, 'Only automatic campaigns can be switched on or off.');
        $oldValues = ['status' => $campaign->status];
        if ($campaign->status === EmailCampaign::STATUS_ACTIVE) {
            $this->campaigns->cancel($campaign);
        } else {
            $campaign->forceFill(['status' => EmailCampaign::STATUS_ACTIVE, 'activated_at' => now(), 'updated_by_admin_id' => auth('admin')->id()])->save();
        }
        AdminAuditService::log($campaign, $oldValues);

        return back()->with('success', $campaign->status === EmailCampaign::STATUS_ACTIVE
            ? "'{$campaign->name}' is on. It sends when its trigger next happens."
            : "'{$campaign->name}' is off. Emails that had not gone out were cancelled.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'destinations' => DestinationModel::query()->orderBy('name')->get(['id', 'name']),
            'packages' => Package::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'promotions' => Promotion::query()->orderByDesc('is_active')->orderByDesc('id')->get(['id', 'name', 'is_active']),
        ];
    }

    private function applyImage(StoreEmailCampaignRequest $request, EmailCampaign $campaign): void
    {
        if ($request->hasFile('image')) {
            if ($campaign->image_path) {
                Storage::disk('public')->delete($campaign->image_path);
            }
            $campaign->image_path = $request->file('image')->store('email-campaigns', 'public');
        } elseif ($request->boolean('remove_image') && $campaign->image_path) {
            Storage::disk('public')->delete($campaign->image_path);
            $campaign->image_path = null;
        }
    }

    /**
     * Save as a draft, schedule it, or switch an automatic campaign on.
     */
    private function applyIntent(EmailCampaign $campaign, string $intent): void
    {
        if ($campaign->isAutomatic()) {
            $wasActive = $campaign->getOriginal('status') === EmailCampaign::STATUS_ACTIVE;
            $campaign->status = $intent === 'activate' ? EmailCampaign::STATUS_ACTIVE : EmailCampaign::STATUS_PAUSED;
            if ($campaign->status === EmailCampaign::STATUS_ACTIVE && ! $wasActive) {
                $campaign->activated_at = now();
            }

            return;
        }
        $campaign->status = $campaign->send_mode === EmailCampaign::MODE_SCHEDULED && $intent === 'schedule'
            ? EmailCampaign::STATUS_SCHEDULED : EmailCampaign::STATUS_DRAFT;
    }

    private function savedMessage(EmailCampaign $campaign): string
    {
        return match ($campaign->status) {
            EmailCampaign::STATUS_SCHEDULED => 'Campaign scheduled for '.$campaign->scheduled_at->timezone('Asia/Manila')->format('M j, Y g:i A').' (Philippine time).',
            EmailCampaign::STATUS_ACTIVE => 'Automatic campaign saved and on.',
            EmailCampaign::STATUS_PAUSED => 'Automatic campaign saved. It stays off until you switch it on.',
            default => 'Draft saved. Preview it and send yourself a test before sending.',
        };
    }

    private function renderPreview(EmailCampaign $campaign): string
    {
        $payload = $this->campaigns->payload($campaign, $this->campaigns->previewContext($campaign), null, '#unsubscribe');
        $payload['greeting'] = 'Hi Juan,';

        return view('emails.promotional', ['payload' => $payload])->render();
    }
}
