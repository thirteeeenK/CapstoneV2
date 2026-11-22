<?php

use App\Services\EmailCampaignService;
use App\Services\TurnstileService;
use Illuminate\Support\Facades\URL;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        'address' => '123 Test St, Manila', 'phone_number' => '09123456789',
        'age_confirmed' => '1', 'terms_accepted' => '1', 'privacy_accepted' => '1', 'ai_disclosure_accepted' => '1',
    ], $overrides);
}

it('records marketing consent only when the optional box is ticked at sign-up', function (array $input, bool $optedIn) {
    $this->mock(TurnstileService::class, fn ($mock) => $mock->shouldReceive('verify')->andReturnNull());

    $this->post('/register', registrationPayload($input))->assertRedirect(route('onboarding.index', absolute: false));

    $user = auth()->user()->fresh();
    expect($user->trip_marketing_enabled)->toBe($optedIn)->and($user->marketing_consent_at !== null)->toBe($optedIn);
})->with([
    'ticked' => [['marketing_opt_in' => '1'], true],
    'left unticked' => [[], false],
]);

it('shows a confirmation for the unsubscribe link and unsubscribes only on confirming', function () {
    $user = onboardedUser();
    $user->setMarketingConsent(true);
    $url = app(EmailCampaignService::class)->unsubscribeUrl($user);

    $this->get($url)->assertOk()->assertSee('Stop promotional emails?');
    expect($user->fresh()->trip_marketing_enabled)->toBeTrue();

    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('You’re unsubscribed');
    expect($user->fresh())->trip_marketing_enabled->toBeFalse()->marketing_unsubscribed_at->not->toBeNull()
        ->trip_reminders_enabled->toBeTrue();
});

it('rejects an unsubscribe link that was not signed for that traveler', function () {
    $user = onboardedUser();
    $user->setMarketingConsent(true);
    $other = onboardedUser();
    $forged = str_replace('/email/unsubscribe/'.$other->id, '/email/unsubscribe/'.$user->id, URL::signedRoute('marketing.unsubscribe', ['user' => $other->id]));

    $this->post($forged)->assertForbidden();
    $this->post(route('marketing.unsubscribe', ['user' => $user->id]))->assertForbidden();
    expect($user->fresh()->trip_marketing_enabled)->toBeTrue();
});

it('saves email preferences from the profile page for the signed-in traveler only', function () {
    $user = onboardedUser();

    $this->patch(route('profile.email-preferences'), ['reminders' => '1', 'marketing' => '1'])->assertRedirect(route('login'));
    $this->actingAs($user)->patch(route('profile.email-preferences'), ['reminders' => '0', 'marketing' => '1'])
        ->assertRedirect(route('profile.edit').'#email-preferences');

    expect($user->fresh())->trip_reminders_enabled->toBeFalse()->trip_marketing_enabled->toBeTrue()->marketing_consent_at->not->toBeNull();
    $this->get(route('profile.edit'))->assertOk()->assertSee('id="email-preferences"', false);
});
