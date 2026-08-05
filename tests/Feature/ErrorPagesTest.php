<?php

use Illuminate\Support\Facades\Route;

it('renders the custom 404 page for missing routes', function () {
    $this->get('/this-page-does-not-exist')
        ->assertStatus(404)
        ->assertSee('Lost at sea')
        ->assertSee('Browse Destinations');
});

it('renders a custom page for every supported error status', function (int $status, string $copy) {
    Route::get('/_err/{code}', fn (int $code) => abort($code))->where('code', '[0-9]+');

    $this->get("/_err/{$status}")
        ->assertStatus($status)
        ->assertSee($copy)
        ->assertSee('SunnyTrips');
})->with([
    [400, "didn't make sense"],
    [401, 'Sign in to continue'],
    [403, 'This beach is private'],
    [419, 'Your session drifted away'],
    [503, 'The tide is out'],
]);

it('renders the custom 429 page when a route is throttled', function () {
    Route::get('/_throttled', fn () => 'ok')->middleware('throttle:2,1');

    $this->get('/_throttled')->assertOk();
    $this->get('/_throttled')->assertOk();

    $this->get('/_throttled')
        ->assertStatus(429)
        ->assertSee('Easy, sailor');
});

it('renders the custom 500 page when debug mode is off', function () {
    config()->set('app.debug', false);

    Route::get('/_err500', fn () => abort(500));

    $this->get('/_err500')
        ->assertStatus(500)
        ->assertSee('The crew hit rough waters');
});

it('keeps the signed-in dashboard link on error pages for authenticated users', function () {
    $user = \App\Models\User::factory()->create();

    Route::get('/_err404', fn () => abort(404));

    $this->actingAs($user)
        ->get('/_err404')
        ->assertSee('Return to Dashboard');
});

it('returns users to their intended destination after signing in again', function () {
    $user = \App\Models\User::factory()->create();

    $this->post(route('login', ['redirect' => url('/destinations')]), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(url('/destinations'));
});

it('ignores redirect targets outside the application host', function () {
    $user = \App\Models\User::factory()->create();

    $this->post(route('login', ['redirect' => 'https://evil.example.com']), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});
