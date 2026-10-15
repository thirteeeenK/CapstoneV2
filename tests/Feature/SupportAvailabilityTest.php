<?php

use App\Models\ChatSession;
use App\Models\SupportInquiry;
use App\Models\SystemSetting;
use App\Services\Support\SupportAvailabilityService;
use App\Services\Support\SupportQueueService;
use Carbon\CarbonImmutable;

test('support hours use Philippine time with an exclusive closing boundary', function (string $time, bool $expected) {
    $support = config('system_settings.support');
    $support['enforce_hours'] = true;
    SystemSetting::factory()->create(['section' => 'support', 'values' => $support]);
    $this->travelTo(CarbonImmutable::parse('2026-10-19 '.$time, 'Asia/Manila'));

    expect(app(SupportAvailabilityService::class)->status()['available'])->toBe($expected);
})->with(['before opening' => ['07:59', false], 'opening' => ['08:00', true], 'before closing' => ['19:59', true], 'closing' => ['20:00', false]]);

test('closed days and all-day support are evaluated correctly', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-19 23:59', 'Asia/Manila'));
    $support = config('system_settings.support');
    $support['enforce_hours'] = true;
    $support['schedule']['mon']['all_day'] = true;
    $support['schedule']['tue']['enabled'] = false;
    SystemSetting::factory()->create(['section' => 'support', 'values' => $support]);
    expect(app(SupportAvailabilityService::class)->status()['available'])->toBeTrue();
    $this->travel(2)->minutes();
    expect(app(SupportAvailabilityService::class)->status()['available'])->toBeFalse();
});

test('unavailable handoffs return a useful response without creating a ticket', function () {
    $support = config('system_settings.support');
    $support['accept_requests'] = false;
    $support['unavailable_message'] = 'Our office is closed today.';
    SystemSetting::factory()->create(['section' => 'support', 'values' => $support]);
    $session = ChatSession::create(['session_token' => ChatSession::generateToken()]);

    $this->postJson('/chat/handoff', ['session_token' => $session->session_token])
        ->assertOk()->assertJsonPath('status', 'support_unavailable')->assertJsonPath('message', 'Our office is closed today.');
    $this->assertDatabaseCount('support_inquiries', 0);
});

test('closing intake preserves existing pending tickets and admin replies', function () {
    $session = ChatSession::create(['session_token' => ChatSession::generateToken()]);
    $inquiry = SupportInquiry::create([
        'ticket_number' => 'TKT-EXISTING', 'chat_session_id' => $session->id,
        'status' => SupportInquiry::STATUS_PENDING, 'requested_at' => now(),
    ]);
    $support = config('system_settings.support');
    $support['accept_requests'] = false;
    SystemSetting::factory()->create(['section' => 'support', 'values' => $support]);

    $this->postJson('/chat/handoff', ['session_token' => $session->session_token])
        ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('ticket_number', 'TKT-EXISTING');
    $queue = app(SupportQueueService::class);
    $claimed = $queue->claimInquiry($inquiry->id, settingsAdmin()->id);
    $message = $queue->sendAdminMessage($claimed, 'We are still helping you.');
    expect($message->message)->toBe('We are still helping you.');
    $this->assertDatabaseCount('support_inquiries', 1);
});
