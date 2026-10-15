<?php

use App\Models\User;
use App\Services\RegistrationAbuseService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function protectedRegistration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Traveler', 'email' => 'traveler@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
        'address' => 'Manila', 'phone_number' => '09123456789',
        'age_confirmed' => '1', 'terms_accepted' => '1',
        'privacy_accepted' => '1', 'ai_disclosure_accepted' => '1',
        'cf-turnstile-response' => 'test-token',
    ], $overrides);
}

beforeEach(function () {
    config(['services.turnstile.site_key' => 'test-site', 'services.turnstile.secret_key' => 'test-secret',
        'services.turnstile.hostname' => 'sunnytripsv2.up.railway.app']);
});

test('registration requires a verified challenge before creating a user', function () {
    $this->post('/register', protectedRegistration(['cf-turnstile-response' => '']))
        ->assertSessionHasErrors('cf-turnstile-response');
    expect(User::count())->toBe(0);
    Http::assertNothingSent();
});

test('registration rejects invalid provider responses', function (array $providerResponse) {
    Http::fake(['challenges.cloudflare.com/*' => Http::response($providerResponse)]);
    $this->post('/register', protectedRegistration())->assertSessionHasErrors('cf-turnstile-response');
    expect(User::count())->toBe(0);
})->with([
    'failure' => [['success' => false]],
    'wrong host' => [['success' => true, 'hostname' => 'attacker.example', 'action' => 'register']],
    'wrong action' => [['success' => true, 'hostname' => 'sunnytripsv2.up.railway.app', 'action' => 'login']],
    'wrong type' => [['success' => 'true', 'hostname' => 'sunnytripsv2.up.railway.app', 'action' => 'register']],
]);

test('registration limits malformed submissions before calling cloudflare', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/register', [])->assertUnprocessable();
    }
    $this->postJson('/register', [])->assertTooManyRequests()->assertHeader('Retry-After');
    Http::assertNothingSent();
});

test('verified registration preserves consent and onboarding', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response([
        'success' => true, 'hostname' => 'sunnytripsv2.up.railway.app', 'action' => 'register',
    ])]);
    $this->post('/register', protectedRegistration())->assertRedirect(route('onboarding.index'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'traveler@example.com', 'terms_version' => config('legal.documents.terms.version')]);
    Http::assertSentCount(1);
});

test('provider connectivity and malformed responses fail closed', function (bool $connectionFails) {
    Http::fake(['challenges.cloudflare.com/*' => $connectionFails ? Http::failedConnection() : Http::response('not-json')]);
    $this->post('/register', protectedRegistration())->assertSessionHasErrors('cf-turnstile-response');
    expect(User::count())->toBe(0)->and(session('_old_input.cf-turnstile-response'))->toBeNull();
})->with([true, false]);

test('missing turnstile configuration cannot bypass registration', function () {
    config(['services.turnstile.secret_key' => null]);
    $this->post('/register', protectedRegistration())->assertSessionHasErrors('cf-turnstile-response');
    expect(User::count())->toBe(0);
    Http::assertNothingSent();
});

test('a busy creation gate rejects registration without creating an account', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response([
        'success' => true, 'hostname' => 'sunnytripsv2.up.railway.app', 'action' => 'register',
    ])]);
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException);
    $cache = Mockery::mock(Cache::getFacadeRoot());
    $cache->shouldReceive('lock')->once()->andReturn($lock);
    Cache::swap($cache);

    $this->post('/register', protectedRegistration())->assertTooManyRequests()->assertHeader('Retry-After', '3');
    expect(User::count())->toBe(0);
});

test('hourly registration limit survives minute resets and later expires', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/register', [])->assertUnprocessable();
        $this->travel(61)->seconds();
    }
    $this->postJson('/register', [])->assertTooManyRequests();
    $this->travel(3601)->seconds();
    $this->postJson('/register', [])->assertUnprocessable();
});

test('password registration and new google accounts share the creation budget but returning login does not', function () {
    config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
    $ip = '127.0.0.1';
    for ($i = 0; $i < 20; $i++) {
        app(RegistrationAbuseService::class)->reserve($ip);
    }
    Http::fake(['challenges.cloudflare.com/*' => Http::response([
        'success' => true, 'hostname' => 'sunnytripsv2.up.railway.app', 'action' => 'register',
    ])]);
    $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/register', protectedRegistration())->assertTooManyRequests();

    $google = (new GoogleUser)->setRaw(['email_verified' => true])->map([
        'id' => 'google-budget-user', 'email' => 'google-budget@example.com', 'name' => 'Budget user',
    ]);
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($google);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    $this->get(route('auth.google.callback'))->assertTooManyRequests();
    expect(User::count())->toBe(0);

    $user = User::factory()->create(['email' => 'google-budget@example.com', 'google_id' => 'google-budget-user']);
    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});
