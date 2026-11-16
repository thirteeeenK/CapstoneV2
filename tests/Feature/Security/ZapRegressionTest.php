<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('adds defensive security headers to application responses', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain('ws: wss:');
});

it('adds HSTS only to secure production responses', function () {
    $this->app->instance('env', 'production');

    $request = Request::create('https://sunnytrips.test/login');
    $response = app(SecurityHeaders::class)->handle(
        $request,
        fn (): Response => new Response,
    );

    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000')
        ->and($response->headers->get('Content-Security-Policy'))
        ->not->toContain('http:')
        ->not->toContain('ws:');
});

it('restricts the production CSP to an explicit allowlist instead of wildcard schemes', function () {
    $this->app->instance('env', 'production');

    $response = app(SecurityHeaders::class)->handle(
        Request::create('https://sunnytrips.test/login'),
        fn (): Response => new Response,
    );

    $directives = collect(explode(';', (string) $response->headers->get('Content-Security-Policy')))
        ->map(fn (string $directive): string => trim($directive))
        ->filter()
        ->mapWithKeys(function (string $directive): array {
            $parts = explode(' ', $directive);

            return [array_shift($parts) => $parts];
        });

    foreach (['script-src', 'style-src', 'font-src', 'connect-src', 'frame-src', 'media-src', 'form-action'] as $directive) {
        expect($directives[$directive])->not->toContain('https:')->not->toContain('*');
    }

    expect($directives['form-action'])->toBe([
        "'self'",
        'https://accounts.google.com',
        'https://checkout.stripe.com',
        'https://*.paymongo.com',
    ])->and($directives['script-src'])->toContain('https://challenges.cloudflare.com');
});

it('does not expose a JavaScript-readable XSRF-TOKEN cookie', function () {
    $response = $this->get('/login');

    $response->assertOk()->assertCookieMissing('XSRF-TOKEN');
});

it('sends redirect responses without a body', function () {
    $response = $this->get('/checkout');

    $response->assertRedirect(route('login'));
    expect($response->getContent())->toBe('');
});

it('rejects malformed public resource identifiers without reaching the database', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with([
    'destination placeholder' => '/destinations/$1',
    'hotel template expression' => '/hotels/${url}',
]);

it('does not allow a SQL injection payload to bypass login', function () {
    $user = User::factory()->create([
        'email' => 'traveler@example.com',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => "' OR 1=1 --",
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects a SQL injection payload in registration email input', function () {
    $response = $this->post('/register', [
        'name' => 'ZAP Validation User',
        'email' => "zap@example.com' OR '1'='1' --",
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test Street, Manila',
        'phone_number' => '09123456789',
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('users', ['name' => 'ZAP Validation User']);
    $this->assertGuest();
});

it('renders review search input as text instead of executable markup', function () {
    $payload = '\"><script>alert(1)</script>';

    $response = $this->get(route('reviews.index', ['search' => $payload]));

    $response
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});
