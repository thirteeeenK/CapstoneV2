<?php

use App\Jobs\SendCampaignMail;
use App\Mail\PromotionalMail;
use App\Models\Booking;
use App\Models\DestinationModel;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\EmailCampaignService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-11-20 10:00', 'Asia/Manila'));
    $this->admin = settingsAdmin();
});

function subscriber(array $attributes = []): User
{
    $user = onboardedUser($attributes);
    $user->setMarketingConsent(true);

    return $user;
}

function campaignLimits(int $dailyCap, int $frequencyDays = 3): void
{
    SystemSetting::query()->updateOrCreate(['section' => 'marketing'], ['values' => ['daily_cap' => $dailyCap, 'frequency_days' => $frequencyDays], 'revision' => 1]);
}

function sentTo(User $user): int
{
    return Mail::sent(PromotionalMail::class, fn (PromotionalMail $mail) => $mail->hasTo($user->email))->count();
}

it('reaches only opted-in, unrestricted travelers who match the filters', function () {
    $boracay = subscriber();
    UserPreference::create(['user_id' => $boracay->id, 'destination' => 'Boracay', 'traveler_type' => 'Couple']);
    $elNido = subscriber();
    UserPreference::create(['user_id' => $elNido->id, 'destination' => 'El Nido', 'traveler_type' => 'Couple']);
    onboardedUser();
    subscriber(['ban_level' => User::BAN_LEVEL_PERMANENT, 'banned_at' => now()]);
    subscriber()->delete();
    $audience = app(EmailCampaignService::class);

    expect($audience->audience([])->pluck('id')->sort()->values()->all())->toBe(collect([$boracay->id, $elNido->id])->sort()->values()->all())
        ->and($audience->audience(['destinations' => ['Boracay']])->pluck('id')->all())->toBe([$boracay->id])
        ->and($audience->audience(['traveler_types' => ['Family with Kids']])->count())->toBe(0);
});

it('segments past travelers, travelers who never booked and inactive ones', function (string $segment, Closure $expected) {
    $traveler = subscriber();
    Booking::factory()->completed()->create(['user_id' => $traveler->id, 'created_at' => now()->subDays(200)]);
    $newcomer = subscriber();

    $ids = app(EmailCampaignService::class)->audience(['segment' => $segment, 'inactive_days' => 90])->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe($expected($traveler, $newcomer));
})->with([
    'past travelers' => ['past_travelers', fn ($traveler) => [$traveler->id]],
    'never booked' => ['never_booked', fn ($traveler, $newcomer) => [$newcomer->id]],
    'inactive for 90 days' => ['inactive', fn ($traveler, $newcomer) => [$traveler->id, $newcomer->id]],
]);

it('sends a manual campaign once, personalised, with a one-click unsubscribe header', function () {
    $ana = subscriber(['name' => 'Ana Reyes']);
    $notOptedIn = onboardedUser();
    $campaign = EmailCampaign::factory()->create();

    $this->actingAs($this->admin, 'admin')->post(route('admin.email-campaigns.send', $campaign))
        ->assertRedirect(route('admin.email-campaigns.show', $campaign))->assertSessionHas('success', fn ($message) => str_contains($message, 'Sending to 1 traveler'));
    $this->post(route('admin.email-campaigns.send', $campaign))->assertSessionHas('error');
    app(EmailCampaignService::class)->tick();

    Mail::assertSent(PromotionalMail::class, 1);
    Mail::assertSent(PromotionalMail::class, fn (PromotionalMail $mail) => $mail->hasTo($ana->email) && $mail->payload['subject'] === 'Island deals for Ana'
        && str_contains($mail->headers()->text['List-Unsubscribe'], '/email/unsubscribe/'.$ana->id)
        && $mail->headers()->text['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click');
    expect(sentTo($notOptedIn))->toBe(0)->and($campaign->fresh()->status)->toBe(EmailCampaign::STATUS_SENT);
});

it('holds emails over the daily cap until the next Philippine day', function () {
    campaignLimits(2);
    $travelers = collect(range(1, 3))->map(fn () => subscriber());
    $service = app(EmailCampaignService::class);

    $service->sendNow(EmailCampaign::factory()->create());
    Mail::assertSent(PromotionalMail::class, 2);
    expect(EmailCampaignRecipient::where('status', 'pending')->count())->toBe(1);

    $this->travelTo(Carbon::parse('2026-11-20 23:59', 'Asia/Manila'));
    $service->tick();
    Mail::assertSent(PromotionalMail::class, 2);

    $this->travelTo(Carbon::parse('2026-11-21 00:01', 'Asia/Manila'));
    $service->tick();
    Mail::assertSent(PromotionalMail::class, 3);
    expect($travelers->every(fn (User $user) => sentTo($user) === 1))->toBeTrue();
});

it('skips a traveler who received another promotion within the frequency gap', function () {
    $user = subscriber();
    $service = app(EmailCampaignService::class);
    $service->sendNow(EmailCampaign::factory()->create());

    $this->travelTo(now()->addDays(2));
    $service->sendNow(EmailCampaign::factory()->create());
    $this->travelTo(now()->addDays(2));
    $service->sendNow(EmailCampaign::factory()->create());

    expect(sentTo($user))->toBe(2)
        ->and(EmailCampaignRecipient::where('status', 'skipped')->value('status_reason'))->toBe('emailed_recently');
});

it('checks consent and the linked promotion again just before sending', function (Closure $change, string $reason) {
    Queue::fake([SendCampaignMail::class]);
    $user = subscriber();
    $promotion = Promotion::factory()->create();
    $service = app(EmailCampaignService::class);
    $service->sendNow(EmailCampaign::factory()->create(['promotion_id' => $promotion->id]));
    $change($user, $promotion);

    (new SendCampaignMail(EmailCampaignRecipient::sole()->id))->handle($service);

    Mail::assertNothingSent();
    expect(EmailCampaignRecipient::sole())->status->toBe('skipped')->status_reason->toBe($reason);
})->with([
    'unsubscribed meanwhile' => [fn (User $user) => $user->setMarketingConsent(false), 'unsubscribed'],
    'promotion paused meanwhile' => [fn (User $user, Promotion $promotion) => $promotion->update(['is_active' => false]), 'promotion_ended'],
]);

it('stops a campaign so nothing still waiting goes out', function () {
    campaignLimits(1);
    subscriber();
    subscriber();
    $campaign = EmailCampaign::factory()->create();
    $service = app(EmailCampaignService::class);
    $service->sendNow($campaign);

    $this->actingAs($this->admin, 'admin')->post(route('admin.email-campaigns.cancel', $campaign))->assertSessionHas('success');
    $this->travelTo(now()->addDay());
    $service->tick();

    Mail::assertSent(PromotionalMail::class, 1);
    expect($campaign->fresh()->status)->toBe(EmailCampaign::STATUS_CANCELLED)->and(EmailCampaignRecipient::where('status', 'cancelled')->count())->toBe(1);
});

it('sends a scheduled campaign when its time comes, not before', function () {
    $user = subscriber();
    $campaign = EmailCampaign::factory()->scheduled(now()->addHour())->create();
    $service = app(EmailCampaignService::class);

    $service->tick();
    expect(sentTo($user))->toBe(0);

    $this->travelTo(now()->addHour());
    $service->tick();
    $service->tick();
    expect(sentTo($user))->toBe(1)->and($campaign->fresh()->status)->toBe(EmailCampaign::STATUS_SENT);
});

it('announces a promotion once when it goes live while the automatic campaign is on', function () {
    $user = subscriber();
    EmailCampaign::factory()->automatic()->create(['subject' => '{promotion}: {discount}']);

    $promotion = Promotion::factory()->create(['name' => 'Summer Splash', 'percent_off' => 15]);
    $promotion->update(['name' => 'Summer Splash Extended']);
    $promotion->update(['is_active' => false]);
    $promotion->update(['is_active' => true]);

    expect(sentTo($user))->toBe(1);
    Mail::assertSent(PromotionalMail::class, fn (PromotionalMail $mail) => str_starts_with($mail->payload['subject'], 'Summer Splash: 15% off'));
});

it('announces a promotion scheduled to start later when its start passes', function () {
    $user = subscriber();
    EmailCampaign::factory()->automatic()->create();
    Promotion::factory()->create(['redeem_starts_at' => now()->addHours(2)]);
    expect(sentTo($user))->toBe(0);

    $this->travelTo(now()->addHours(2)->addMinute());
    app(EmailCampaignService::class)->tick();

    expect(sentTo($user))->toBe(1);
});

it('never announces promotions that went live before the campaign was switched on, or while it is off', function () {
    $user = subscriber();
    $campaign = EmailCampaign::factory()->automatic()->create(['status' => EmailCampaign::STATUS_PAUSED, 'activated_at' => null]);
    Promotion::factory()->create();

    $this->actingAs($this->admin, 'admin')->post(route('admin.email-campaigns.toggle', $campaign))->assertSessionHas('success');
    app(EmailCampaignService::class)->tick();

    expect($campaign->fresh()->status)->toBe(EmailCampaign::STATUS_ACTIVE)->and(sentTo($user))->toBe(0);
});

it('announces a tour package when it is published, linking to it', function () {
    $user = subscriber();
    EmailCampaign::factory()->automatic(EmailCampaign::TRIGGER_PACKAGE_PUBLISHED)->create(['subject' => 'New: {package} in {destination}']);
    $destination = DestinationModel::factory()->create(['name' => 'Coron']);

    $package = Package::factory()->create(['destination_id' => $destination->id, 'name' => 'Coron Wreck Dive', 'is_active' => false]);
    expect(sentTo($user))->toBe(0);
    $package->update(['is_active' => true]);
    $package->update(['price' => 9000]);

    Mail::assertSent(PromotionalMail::class, 1);
    Mail::assertSent(PromotionalMail::class, fn (PromotionalMail $mail) => $mail->payload['subject'] === 'New: Coron Wreck Dive in Coron'
        && $mail->payload['cta_url'] === route('packages.index', ['search' => 'Coron Wreck Dive']));
});

it('keeps the admin pages behind admin sign-in', function () {
    $campaign = EmailCampaign::factory()->create();

    $this->get(route('admin.email-campaigns.index'))->assertRedirect(route('admin.login'));
    $this->actingAs(subscriber())->post(route('admin.email-campaigns.send', $campaign))->assertRedirect(route('admin.login'));
    Mail::assertNothingSent();
});

it('saves a draft, schedules it, and switches an automatic campaign on', function (array $input, string $status) {
    $this->actingAs($this->admin, 'admin')->post(route('admin.email-campaigns.store'), array_merge([
        'name' => 'Holy Week', 'subject' => 'Holy Week deals', 'headline' => 'Plan your Holy Week', 'body' => 'Beat the rush.',
        'cta_target' => 'lucky', 'send_mode' => 'manual', 'intent' => 'draft', 'audience' => ['segment' => 'all'],
    ], $input))->assertRedirect();

    $campaign = EmailCampaign::sole();
    expect($campaign->status)->toBe($status)->and($campaign->created_by_admin_id)->toBe($this->admin->id);
    Mail::assertNothingSent();
})->with([
    'manual draft' => [[], EmailCampaign::STATUS_DRAFT],
    'scheduled' => [['send_mode' => 'scheduled', 'intent' => 'schedule', 'scheduled_at' => '2026-11-21T09:00'], EmailCampaign::STATUS_SCHEDULED],
    'automatic on' => [['send_mode' => 'automatic', 'trigger' => 'promotion_live', 'intent' => 'activate'], EmailCampaign::STATUS_ACTIVE],
]);

it('rejects a button link off this site, a past schedule and a missing trigger', function (array $input, string $field, string $message) {
    $this->actingAs($this->admin, 'admin')->post(route('admin.email-campaigns.store'), array_merge([
        'name' => 'Holy Week', 'subject' => 'Holy Week deals', 'headline' => 'Plan your Holy Week', 'body' => 'Beat the rush.',
        'cta_target' => 'lucky', 'send_mode' => 'manual', 'intent' => 'draft',
    ], $input))->assertSessionHasErrors([$field => $message]);

    expect(EmailCampaign::count())->toBe(0);
})->with([
    'external link' => [['cta_target' => 'custom', 'cta_path' => 'https://evil.example/login'], 'cta_path', 'The button link must be a page on this site starting with "/", for example /destinations.'],
    'protocol-relative link' => [['cta_target' => 'custom', 'cta_path' => '//evil.example'], 'cta_path', 'The button link must be a page on this site starting with "/", for example /destinations.'],
    'schedule in the past' => [['send_mode' => 'scheduled', 'intent' => 'schedule', 'scheduled_at' => '2026-11-20T09:00'], 'scheduled_at', 'Choose a send time in the future (Philippine time).'],
    'automatic without trigger' => [['send_mode' => 'automatic', 'intent' => 'activate'], 'trigger', 'Choose what makes this automatic campaign send.'],
]);

it('refuses to edit a campaign that has already sent', function () {
    subscriber();
    $campaign = EmailCampaign::factory()->create();
    app(EmailCampaignService::class)->sendNow($campaign);

    $this->actingAs($this->admin, 'admin')->get(route('admin.email-campaigns.edit', $campaign))->assertRedirect(route('admin.email-campaigns.show', $campaign));
    $this->put(route('admin.email-campaigns.update', $campaign), ['name' => 'Changed', 'subject' => 'Changed', 'headline' => 'Changed', 'body' => 'Changed body.',
        'cta_target' => 'lucky', 'send_mode' => 'manual', 'intent' => 'draft'])->assertRedirect(route('admin.email-campaigns.show', $campaign));
    expect($campaign->fresh()->name)->not->toBe('Changed');
});

it('previews the email with typed content escaped', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.email-campaigns.preview'), [
        'subject' => 'Deals', 'headline' => '<script>alert(1)</script>Big sale', 'body' => "First <b>line</b>\n\nSecond line", 'cta_target' => 'lucky',
    ])->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;Big sale', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('First &lt;b&gt;line&lt;/b&gt;', false)->assertSee(route('lucky.index'), false);
});

it('counts the audience live and sends a test only to the admin', function () {
    subscriber();
    subscriber();
    $campaign = EmailCampaign::factory()->create();

    $this->actingAs($this->admin, 'admin')->postJson(route('admin.email-campaigns.audience-count'), ['audience' => ['segment' => 'all']])->assertExactJson(['count' => 2]);
    $this->post(route('admin.email-campaigns.test', $campaign))->assertSessionHas('success');

    Mail::assertSent(PromotionalMail::class, 1);
    Mail::assertSent(PromotionalMail::class, fn (PromotionalMail $mail) => $mail->hasTo($this->admin->email) && str_starts_with($mail->payload['subject'], '[Test] '));
    expect(EmailCampaignRecipient::count())->toBe(0);
});
