<?php

use App\Models\User;
use App\Models\UserPreference;
use App\Services\TurnstileService;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Build the profile object Socialite's Google driver would return.
 */
function googleProfile(array $overrides = []): SocialiteUser
{
    $attributes = array_merge([
        'id' => 'google-sub-10294',
        'name' => 'Juan dela Cruz',
        'email' => 'juan@example.com',
        'avatar' => 'https://lh3.googleusercontent.com/a/juan=s96-c',
        'email_verified' => true,
    ], $overrides);

    return (new SocialiteUser)
        ->setRaw([
            'sub' => $attributes['id'],
            'email' => $attributes['email'],
            'email_verified' => $attributes['email_verified'],
        ])
        ->map([
            'id' => $attributes['id'],
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'avatar' => $attributes['avatar'],
        ]);
}

/**
 * Stand in for the round trip to Google, which the suite never actually makes.
 */
function fakeGoogleCallback(SocialiteUser|Throwable $result): void
{
    $provider = Mockery::mock(Provider::class);

    $result instanceof Throwable
        ? $provider->shouldReceive('user')->andThrow($result)
        : $provider->shouldReceive('user')->andReturn($result);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('the google button is shown on the login and register screens', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('action="'.route('auth.google.redirect').'"', false)
        ->assertSee('Continue with Google');

    $this->get('/register')
        ->assertOk()
        ->assertSee('action="'.route('auth.google.redirect').'"', false)
        ->assertSee('Sign up with Google');
});

test('the google form is not nested inside the credentials form', function (string $page) {
    // Nested forms are invalid HTML and the browser silently drops the inner
    // one, which would take the Turnstile token with it.
    $html = $this->get($page)->assertOk()->getContent();

    expect(strpos($html, '</form>'))->toBeLessThan(strpos($html, route('auth.google.redirect')));
})->with(['/login', '/register']);

test('the google button is hidden when no credentials are configured', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Continue with Google');
});

test('the redirect leg hands the traveler to google', function () {
    $this->post(route('auth.google.redirect'))
        ->assertRedirectContains('accounts.google.com');
});

test('the redirect leg bounces back to login when google is not configured', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->post(route('auth.google.redirect'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});

test('the redirect leg is unreachable by get', function () {
    // A GET variant would be an unverified way into the same OAuth flow.
    $this->get(route('auth.google.redirect'))->assertStatus(405);
});

test('a new traveler is registered from their google profile and sent to onboarding', function () {
    fakeGoogleCallback(googleProfile());

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('onboarding.index', absolute: false));
    $response->assertSessionHas('success');

    $user = User::where('email', 'juan@example.com')->sole();

    expect($user->google_id)->toBe('google-sub-10294')
        ->and($user->name)->toBe('Juan dela Cruz')
        ->and($user->avatar)->toBe('https://lh3.googleusercontent.com/a/juan=s96-c')
        ->and($user->password)->toBeNull()
        ->and($user->hasPassword())->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('the clickwrap consent on the google button is stamped onto the new account', function () {
    fakeGoogleCallback(googleProfile());

    $this->get(route('auth.google.callback'));

    $user = User::where('email', 'juan@example.com')->sole();

    expect($user->terms_accepted_at)->not->toBeNull()
        ->and($user->privacy_accepted_at)->not->toBeNull()
        ->and($user->ai_disclosure_accepted_at)->not->toBeNull()
        ->and($user->terms_version)->toBe(config('legal.documents.terms.version'))
        ->and($user->privacy_version)->toBe(config('legal.documents.privacy.version'))
        ->and($user->ai_disclosure_version)->toBe(config('legal.documents.ai_disclosure.version'))
        ->and($user->consent_ip_address)->not->toBeNull();
});

test('a google profile is matched to an existing password account by email', function () {
    $existing = User::factory()->unverified()->create(['email' => 'juan@example.com']);

    fakeGoogleCallback(googleProfile());

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($existing);

    $existing->refresh();

    expect($existing->google_id)->toBe('google-sub-10294')
        ->and($existing->email_verified_at)->not->toBeNull()
        ->and($existing->hasPassword())->toBeTrue();

    expect(User::where('email', 'juan@example.com')->count())->toBe(1);
});

test('a returning traveler is matched on google id even after changing their email', function () {
    $existing = User::factory()->create([
        'email' => 'new-address@example.com',
        'google_id' => 'google-sub-10294',
        'email_verified_at' => null,
    ]);

    fakeGoogleCallback(googleProfile(['email' => 'juan@example.com']));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($existing);

    expect($existing->refresh()->email)->toBe('new-address@example.com')
        ->and($existing->email_verified_at)->toBeNull()
        ->and(User::count())->toBe(1);
});

test('google email matching cannot replace a different linked identity', function () {
    $user = User::factory()->create(['email' => 'juan@example.com', 'google_id' => 'original-google']);
    fakeGoogleCallback(googleProfile());
    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('error');
    expect($user->fresh()->google_id)->toBe('original-google');
    $this->assertGuest();
});

test('a google profile with an unverified email cannot claim an account', function () {
    User::factory()->create(['email' => 'juan@example.com']);

    fakeGoogleCallback(googleProfile(['email_verified' => false]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::where('email', 'juan@example.com')->sole()->google_id)->toBeNull();
});

test('a banned traveler signing in with google is sent to the suspended page', function () {
    User::factory()->create([
        'email' => 'juan@example.com',
        'ban_level' => User::BAN_LEVEL_PERMANENT,
        'ban_reason' => 'Terms of service violation.',
        'banned_at' => now(),
    ]);

    fakeGoogleCallback(googleProfile());

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('account.suspended'))
        ->assertSessionHas('suspended');

    $this->assertGuest();
});

test('a failed google callback returns to login with an error instead of an exception', function () {
    fakeGoogleCallback(new InvalidStateException);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('an account inside the deletion grace window is recovered by google sign-in', function () {
    $existing = User::factory()->create(['email' => 'juan@example.com']);
    $existing->delete();

    fakeGoogleCallback(googleProfile());

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    expect($existing->fresh()->trashed())->toBeFalse();
    $this->assertAuthenticatedAs($existing);
});

test('a google-only traveler cannot set a password using only the application session', function () {
    $user = onboardedUser(['password' => null, 'google_id' => 'google-sub-10294']);

    $response = $this->actingAs($user)->put(route('password.update'), [
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    $response->assertSessionHasErrors('confirmation');
    expect($user->fresh()->password)->toBeNull();
});

test('google onboarding starts fresh after an admin permanently deletes the previous customer', function () {
    $user = onboardedUser(['email' => 'juan@example.com', 'password' => null, 'google_id' => 'google-sub-10294']);
    UserPreference::create(['user_id' => $user->id]);
    $this->actingAs(settingsAdmin(), 'admin')->delete(route('admin.users.destroy', $user->id), [
        'mode' => 'permanent', 'confirm_email' => $user->email, 'reason' => 'Reset onboarding test',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->app['auth']->shouldUse('web');
    fakeGoogleCallback(googleProfile());

    $this->get(route('auth.google.callback'))->assertRedirect(route('onboarding.index'));

    $freshUser = User::where('google_id', 'google-sub-10294')->sole();
    $this->assertAuthenticatedAs($freshUser);
    expect($freshUser->id)->not->toBe($user->id);
    expect($freshUser->preferences_embedding)->toBeNull();
    $this->assertDatabaseMissing('user_preferences', ['user_id' => $freshUser->id]);
    $this->get(route('onboarding.index'))->assertOk();
});

test('a traveler with a password still has to confirm it before changing it', function () {
    $user = onboardedUser();

    $this->actingAs($user)
        ->put(route('password.update'), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])
        ->assertSessionHasErrorsIn('updatePassword', ['current_password']);
});

test('a google-only traveler deletes their account by typing their email address', function () {
    $user = onboardedUser(['password' => null, 'google_id' => 'google-sub-10294']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirm_email' => $user->email])
        ->assertRedirect('/');

    $this->assertGuest();
    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('a google-only traveler cannot delete their account with the wrong email address', function () {
    $user = onboardedUser(['password' => null, 'google_id' => 'google-sub-10294']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirm_email' => 'someone-else@example.com'])
        ->assertSessionHasErrorsIn('userDeletion', ['confirm_email']);

    expect($user->fresh()->trashed())->toBeFalse();
    $this->assertAuthenticated();
});

test('the profile page offers a google-only traveler a password instead of a change', function () {
    $user = onboardedUser(['password' => null, 'google_id' => 'google-sub-10294']);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee("you don't have a password yet", false)
        ->assertSee('Set password')
        ->assertDontSee('Current password')
        ->assertSee('Type your email address to confirm.');
});

test('the profile page still asks a password traveler for their current one', function () {
    $user = onboardedUser();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Current password')
        ->assertSee('Update password')
        ->assertSee('Enter your current password to confirm.')
        ->assertDontSee('Type your email address to confirm.');
});

/**
 * Turn the Google button's Turnstile gate on. It is off by default because the
 * test environment ships no TURNSTILE_* keys.
 */
function googleChallengeConfigured(): void
{
    config([
        'services.turnstile.site_key' => 'test-site',
        'services.turnstile.secret_key' => 'test-secret',
        'services.turnstile.hostname' => 'sunnytripsv2.up.railway.app',
    ]);
}

/**
 * Stand in for Cloudflare's siteverify endpoint.
 *
 * @param  array<string, mixed>  $payload
 */
function fakeSiteverify(array $payload): void
{
    Http::fake(['challenges.cloudflare.com/*' => Http::response($payload)]);
}

test('a verified challenge hands the traveler to google', function () {
    googleChallengeConfigured();
    fakeSiteverify([
        'success' => true,
        'hostname' => 'sunnytripsv2.up.railway.app',
        'action' => TurnstileService::ACTION_GOOGLE,
    ]);

    $this->from('/login')
        ->post(route('auth.google.redirect'), ['cf-turnstile-response' => 'test-token'])
        ->assertRedirectContains('accounts.google.com');

    Http::assertSentCount(1);
});

test('a failed challenge returns the traveler to the page they started from', function () {
    googleChallengeConfigured();
    fakeSiteverify(['success' => false]);

    $this->from('/register')
        ->post(route('auth.google.redirect'), ['cf-turnstile-response' => 'rejected-token'])
        ->assertRedirect('/register')
        ->assertSessionHas('error')
        // Not a cf-turnstile-response validation error: that key is what makes
        // the register page re-open its consent modal.
        ->assertSessionHasNoErrors();
});

test('a registration token cannot be replayed at the google button', function () {
    googleChallengeConfigured();
    fakeSiteverify([
        'success' => true,
        'hostname' => 'sunnytripsv2.up.railway.app',
        'action' => TurnstileService::ACTION_REGISTER,
    ]);

    $this->from('/login')
        ->post(route('auth.google.redirect'), ['cf-turnstile-response' => 'register-token'])
        ->assertRedirect('/login')
        ->assertSessionHas('error');
});

test('a challenge minted for another site is rejected at the google button', function () {
    googleChallengeConfigured();
    fakeSiteverify([
        'success' => true,
        'hostname' => 'attacker.example',
        'action' => TurnstileService::ACTION_GOOGLE,
    ]);

    $this->from('/login')
        ->post(route('auth.google.redirect'), ['cf-turnstile-response' => 'foreign-token'])
        ->assertRedirect('/login')
        ->assertSessionHas('error');
});

test('a missing challenge token is rejected without calling cloudflare', function () {
    googleChallengeConfigured();
    Http::fake();

    $this->from('/login')
        ->post(route('auth.google.redirect'))
        ->assertRedirect('/login')
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('provider connectivity failures keep the traveler on the sign-in page', function () {
    googleChallengeConfigured();
    Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);

    $this->from('/login')
        ->post(route('auth.google.redirect'), ['cf-turnstile-response' => 'test-token'])
        ->assertRedirect('/login')
        ->assertSessionHas('error');
});

test('the google button is not challenged when turnstile is unconfigured', function () {
    // Deliberate: with no site key no widget can render, so no token could ever
    // exist. Failing closed here would make Google sign-in permanently dead in
    // local dev and CI, and this leg only forwards to Google — account creation
    // happens in callback(), which stays rate limited and state-checked.
    config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);
    Http::fake();

    $this->post(route('auth.google.redirect'))
        ->assertRedirectContains('accounts.google.com');

    Http::assertNothingSent();
});

test('the sign-in button carries a challenge only when turnstile is configured', function () {
    googleChallengeConfigured();

    $this->get('/login')
        ->assertOk()
        ->assertSee('name="cf-turnstile-response"', false)
        ->assertSee('test-site')
        ->assertSee(TurnstileService::ACTION_GOOGLE);

    config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('name="cf-turnstile-response"', false)
        ->assertDontSee('challenges.cloudflare.com');
});
