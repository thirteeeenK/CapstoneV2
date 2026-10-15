<?php

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Hash;
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
        ->assertSee(route('auth.google.redirect'))
        ->assertSee('Continue with Google');

    $this->get('/register')
        ->assertOk()
        ->assertSee(route('auth.google.redirect'))
        ->assertSee('Sign up with Google');
});

test('the google button is hidden when no credentials are configured', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Continue with Google');
});

test('the redirect leg hands the traveler to google', function () {
    $this->get(route('auth.google.redirect'))
        ->assertRedirectContains('accounts.google.com');
});

test('the redirect leg bounces back to login when google is not configured', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get(route('auth.google.redirect'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
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
    ]);

    fakeGoogleCallback(googleProfile(['email' => 'juan@example.com']));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($existing);

    expect($existing->refresh()->email)->toBe('new-address@example.com')
        ->and(User::count())->toBe(1);
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

test('a google-only traveler can set a password without confirming an old one', function () {
    $user = onboardedUser(['password' => null, 'google_id' => 'google-sub-10294']);

    $response = $this->actingAs($user)->put(route('password.update'), [
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect(Hash::check('brand-new-password', $user->refresh()->password))->toBeTrue()
        ->and($user->hasPassword())->toBeTrue();
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

test('a google-only traveler cannot delete their account with the wrong email address', function (bool $gracePeriod) {
    config(['auth.account_deletion_grace_period' => $gracePeriod]);
    $user = onboardedUser(['password' => null, 'google_id' => 'google-sub-10294']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirm_email' => 'someone-else@example.com'])
        ->assertSessionHasErrorsIn('userDeletion', ['confirm_email']);

    expect($user->fresh()->trashed())->toBeFalse();
    $this->assertAuthenticated();
})->with(['grace enabled' => true, 'grace disabled' => false]);

test('google sign-in starts onboarding again after immediate account deletion', function () {
    config(['auth.account_deletion_grace_period' => false]);
    $user = onboardedUser([
        'email' => 'juan@example.com', 'password' => null, 'google_id' => 'google-sub-10294',
    ]);
    UserPreference::create(['user_id' => $user->id]);
    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirm_email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('user_preferences', ['user_id' => $user->id]);
    fakeGoogleCallback(googleProfile());

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('onboarding.index'))
        ->assertSessionMissing('account_recovered');

    $newUser = User::where('google_id', 'google-sub-10294')->sole();
    $this->assertAuthenticatedAs($newUser);
    expect($newUser->id)->not->toBe($user->id);
    expect($newUser->preferences_embedding)->toBeNull();
    $this->assertDatabaseMissing('user_preferences', ['user_id' => $newUser->id]);
    $this->get(route('onboarding.index'))->assertOk();
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
