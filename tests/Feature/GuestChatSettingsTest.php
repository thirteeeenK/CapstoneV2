<?php

use App\Http\Middleware\EnforceGuestChatLimits;
use App\Models\SystemSetting;
use App\Services\SystemSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

function chatLimitProbe(): void
{
    Route::post('/settings-chat-probe', fn () => response()->json(['ok' => true]))->middleware(EnforceGuestChatLimits::class);
}

test('the saved minute cap gives a temporary response and recovers after its window', function () {
    $this->freezeTime();
    chatLimitProbe();
    SystemSetting::factory()->create(['section' => 'chatbot', 'values' => ['daily_limit' => 15, 'minute_limit' => 1]]);
    $this->postJson('/settings-chat-probe')->assertOk();
    $this->postJson('/settings-chat-probe')->assertStatus(429)->assertJsonPath('limit_type', 'minute')
        ->assertJsonPath('retry_after_seconds', 60)->assertJsonPath('status', 'guest_limit_reached');
    $this->travel(61)->seconds();
    $this->postJson('/settings-chat-probe')->assertOk();
});

test('daily limits reset at midnight in Philippine time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-19 23:59:58', 'Asia/Manila'));
    chatLimitProbe();
    SystemSetting::factory()->create(['section' => 'chatbot', 'values' => ['daily_limit' => 1, 'minute_limit' => 5]]);
    $this->postJson('/settings-chat-probe')->assertOk();
    $this->postJson('/settings-chat-probe')->assertStatus(429)->assertJsonPath('limit_type', 'day')
        ->assertJsonPath('retry_after_seconds', 2);
    $this->travel(3)->seconds();
    $this->postJson('/settings-chat-probe')->assertOk();
});

test('editing limits does not erase usage and lower limits apply on the next request', function () {
    chatLimitProbe();
    $this->postJson('/settings-chat-probe')->assertOk();
    app(SystemSettingsService::class)->save('chatbot', ['daily_limit' => 1, 'minute_limit' => 5], 0, settingsAdmin());
    $this->postJson('/settings-chat-probe')->assertStatus(429)->assertJsonPath('limit_type', 'day');
    expect(RateLimiter::attempts('chat-guest-daily:127.0.0.1'))->toBe(1);
});

test('signed-in travelers are not subject to guest allowances', function () {
    chatLimitProbe();
    SystemSetting::factory()->create(['section' => 'chatbot', 'values' => ['daily_limit' => 1, 'minute_limit' => 1]]);
    $this->actingAs(onboardedUser());
    $this->postJson('/settings-chat-probe')->assertOk();
    $this->postJson('/settings-chat-probe')->assertOk();
    expect(RateLimiter::attempts('chat-guest-daily:127.0.0.1'))->toBe(0);
});
