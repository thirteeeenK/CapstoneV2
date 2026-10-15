<?php

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

function fakeConfirmationGoogle(string $id = 'linked-google', bool $verified = true): void
{
    config(['testing.confirmation_google_id' => $id, 'testing.confirmation_google_verified' => $verified]);
    Socialite::shouldReceive('buildProvider')->with(GoogleProvider::class, Mockery::type('array'))
        ->andReturnUsing(function (string $class, array $config) {
            $handler = new MockHandler([
                new Response(200, [], json_encode(['access_token' => 'fake-token', 'token_type' => 'Bearer'])),
                new Response(200, [], json_encode(['sub' => config('testing.confirmation_google_id'), 'name' => 'Traveler', 'email' => 'google@example.com', 'email_verified' => config('testing.confirmation_google_verified')])),
            ]);

            return (new GoogleProvider(request(), $config['client_id'], $config['client_secret'], url($config['redirect'])))
                ->setHttpClient(new Client(['handler' => HandlerStack::create($handler)]));
        });
}

function startConfirmedOperation(object $test, User $user, string $purpose = 'change-email'): string
{
    $response = $test->actingAs($user)->post(route('account-security.start'), [
        'purpose' => $purpose, 'name' => 'Updated name', 'email' => 'new@example.com',
    ])->assertRedirectContains('accounts.google.com');
    $test->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return $query['state'];
}

function finishConfirmedOperation(object $test, string $state): string
{
    $response = $test->get(route('account-security.callback', ['state' => $state, 'code' => 'fake-code']));
    expect($response->headers->get('Location'))->toBe(route('account-security.show'));
    $response = $test->get(route('account-security.show'))->assertOk();

    return $response->viewData('operation')['id'];
}

beforeEach(function () {
    config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret',
        'services.google.reauth_redirect' => '/account-security/google/callback']);
    fakeConfirmationGoogle();
});

test('google confirmation changes only the pending email after explicit final submission', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $state = startConfirmedOperation($this, $user);
    $id = finishConfirmedOperation($this, $state);
    expect($user->fresh()->email)->toBe($user->email);
    $this->post(route('account-security.commit'), ['confirmation_id' => $id, 'email' => 'tampered@example.com'])
        ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe('new@example.com')->and($user->fresh()->name)->toBe('Updated name')
        ->and($user->fresh()->google_id)->toBe('linked-google')->and($user->fresh()->email_verified_at)->toBeNull();
    $this->post(route('account-security.commit'), ['confirmation_id' => $id])->assertSessionHasErrors('confirmation');
});

test('another google identity cannot authorize the change', function () {
    fakeConfirmationGoogle('wrong-google');
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $state = startConfirmedOperation($this, $user);
    $this->get(route('account-security.callback', ['state' => $state, 'code' => 'fake-code']))
        ->assertRedirect(route('profile.edit'))->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email)->and(User::count())->toBe(1);
});

test('missing or wrong oauth state cannot authorize a change', function (mixed $state) {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    startConfirmedOperation($this, $user);
    $this->get(route('account-security.callback', ['state' => $state, 'code' => 'fake-code']))
        ->assertRedirect(route('profile.edit'))->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email);
})->with([null, 'wrong', ['malformed']]);

test('confirmation cannot be committed before provider callback', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    startConfirmedOperation($this, $user);
    $this->post(route('account-security.commit'), ['confirmation_id' => 'invented'])->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email);
});

test('expired provider proof cannot change an email', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    $this->travel(301)->seconds();
    $this->post(route('account-security.commit'), ['confirmation_id' => $id])->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email);
});

test('google proof cannot authorize another purpose', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    $this->put(route('password.update'), ['confirmation_id' => $id, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasErrors('confirmation');
    expect($user->fresh()->password)->toBeNull();
});

test('google only accounts can set their first password with a purpose bound proof', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user, 'set-password'));
    $this->put(route('password.update'), ['confirmation_id' => $id, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors()->assertSessionHas('status', 'password-created');
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('changing the google link invalidates pending confirmation', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    $user->update(['google_id' => 'different-google']);
    $this->post(route('account-security.commit'), ['confirmation_id' => $id])->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email);
});

test('confirmation preserves the ordinary google login state', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $this->withSession(['state' => 'ordinary-login-state']);
    finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    expect(session('state'))->toBe('ordinary-login-state');
});

test('confirmation requires an authenticated account and a linked google identity', function () {
    $this->post(route('account-security.start'), ['purpose' => 'set-password'])->assertRedirect(route('login'));
    $user = onboardedUser(['password' => null, 'google_id' => null]);
    $this->actingAs($user)->post(route('account-security.start'), ['purpose' => 'set-password'])->assertSessionHasErrors('confirmation');
});

test('target email uniqueness is rechecked on final commit', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    User::factory()->create(['email' => 'new@example.com']);
    $this->post(route('account-security.commit'), ['confirmation_id' => $id])->assertSessionHasErrors('email');
    expect($user->fresh()->email)->toBe($user->email);
});

test('an unverified provider identity cannot confirm a change', function () {
    fakeConfirmationGoogle(verified: false);
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $state = startConfirmedOperation($this, $user);
    $this->get(route('account-security.callback', ['state' => $state, 'code' => 'fake-code']))
        ->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email);
});

test('pending google confirmation expires even before the provider returns', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $state = startConfirmedOperation($this, $user);
    $this->travel(601)->seconds();
    $this->get(route('account-security.callback', ['state' => $state, 'code' => 'fake-code']))->assertSessionHasErrors('confirmation');
});

test('replacing a pending operation rejects the older state', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $oldState = startConfirmedOperation($this, $user);
    startConfirmedOperation($this, $user, 'set-password');
    $this->get(route('account-security.callback', ['state' => $oldState, 'code' => 'fake-code']))->assertSessionHasErrors('confirmation');
    expect($user->fresh()->password)->toBeNull();
});

test('a different browser session cannot consume a confirmed operation', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    $this->withCookie(config('session.cookie'), str_repeat('x', 40));
    $this->post(route('account-security.commit'), ['confirmation_id' => $id])->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email);
});

test('a concurrent password addition invalidates google confirmation', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    $user->update(['password' => 'different-password']);
    $this->post(route('account-security.commit'), ['confirmation_id' => $id])->assertSessionHasErrors('confirmation');
});

test('cancelled google operations cannot be committed', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    $this->post(route('account-security.cancel'))->assertRedirect(route('profile.edit'));
    $this->post(route('account-security.commit'), ['confirmation_id' => $id])->assertSessionHasErrors('confirmation');
});

test('a busy confirmation gate cannot partially commit an email change', function () {
    $user = onboardedUser(['google_id' => 'linked-google', 'password' => null]);
    $id = finishConfirmedOperation($this, startConfirmedOperation($this, $user));
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException);
    $cache = Mockery::mock(Cache::getFacadeRoot());
    $cache->shouldReceive('lock')->once()->andReturn($lock);
    Cache::swap($cache);

    $this->post(route('account-security.commit'), ['confirmation_id' => $id])
        ->assertTooManyRequests()->assertHeader('Retry-After', '3');
    expect($user->fresh()->email)->toBe($user->email)->and($user->fresh()->name)->toBe($user->name);
});
