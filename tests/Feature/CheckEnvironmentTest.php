<?php

it('passes when the app key, database and pgvector are ready', function () {
    $this->artisan('app:check-environment')
        ->expectsOutputToContain('is reachable')
        ->assertSuccessful();
});

it('fails with a clear fix when the app key is missing', function () {
    config(['app.key' => null]);

    $this->artisan('app:check-environment')
        ->expectsOutputToContain('APP_KEY is missing')
        ->assertFailed();
});

it('warns without failing when the Gemini key is not set', function () {
    config(['services.gemini.api_key' => null]);

    $this->artisan('app:check-environment')
        ->expectsOutputToContain('GEMINI_API_KEY is not set')
        ->assertSuccessful();
});

it('writes test-run logs to their own file', function () {
    expect(config('logging.channels.stack.channels'))->toBe(['testing'])
        ->and(config('logging.channels.testing.path'))->toEndWith('testing.log');
});
