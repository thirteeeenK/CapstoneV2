<?php

use App\Models\AdminModel;
use App\Models\User;
use App\Notifications\AccountModerationNotice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => [
                'values' => array_fill(0, 3072, 0.01),
            ],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Here is a recommendation for you!'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);
});

it('prepends a warning notice once per chat session for warned users', function () {
    $user = onboardedUser([
        'ban_level' => User::BAN_LEVEL_WARNING,
        'banned_at' => now(),
        'ban_reason' => 'Repeated inappropriate queries.',
    ]);
    $this->actingAs($user);

    $first = $this->postJson('/chat', ['message' => 'Hello there']);
    $first->assertStatus(200)->assertJson(['status' => 'success']);
    expect($first->json('reply'))->toStartWith('⚠️ Account notice:')
        ->and($first->json('reply'))->toContain('Repeated inappropriate queries.');

    $second = $this->postJson('/chat', [
        'message' => 'Hello again',
        'session_token' => $first->json('session_token'),
    ]);
    $second->assertStatus(200)->assertJson(['status' => 'success']);
    expect($second->json('reply'))->not->toStartWith('⚠️ Account notice:');
});

it('does not prepend a warning notice for non-warned users', function () {
    $this->actingAs(onboardedUser());

    $response = $this->postJson('/chat', ['message' => 'Hello there']);

    $response->assertStatus(200)->assertJson(['status' => 'success']);
    expect($response->json('reply'))->not->toStartWith('⚠️ Account notice:');
});

it('lets an admin clear all chatbot abuse flags while keeping report history', function () {
    $admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'flags-admin@sunnytripstest.com',
        'password' => 'password',
    ]);
    $user = onboardedUser(['chatbot_flag_count' => 2]);
    $user->abuseReports()->createMany([
        ['message' => 'bad query one', 'category' => 'Spam', 'reason' => 'test', 'status' => 'pending'],
        ['message' => 'bad query two', 'category' => 'Spam', 'reason' => 'test', 'status' => 'pending'],
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.users.clear-flags', $user->id))
        ->assertRedirect();

    $user->refresh();

    expect($user->chatbot_flag_count)->toBe(0)
        ->and($user->abuseReports()->where('status', 'pending')->count())->toBe(0)
        ->and($user->abuseReports()->count())->toBe(2);
});

it('emails the user when an admin issues a warning, suspension, or permanent ban', function (string $level) {
    Notification::fake();
    $admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'moderation-admin@sunnytripstest.com',
        'password' => 'password',
    ]);
    $user = onboardedUser();

    $payload = ['ban_level' => $level, 'ban_reason' => 'Inappropriate chatbot usage.'];
    if ($level === 'temporary') {
        $payload['ban_duration_days'] = 7;
    }

    $this->actingAs($admin, 'admin')
        ->post(route('admin.users.ban', $user->id), $payload)
        ->assertRedirect();

    Notification::assertSentTo($user, AccountModerationNotice::class, function ($notification) use ($level) {
        return $notification->level === $level
            && $notification->reason === 'Inappropriate chatbot usage.';
    });
})->with(['warning', 'temporary', 'permanent']);

it('emails the user when an admin restores their account', function () {
    Notification::fake();
    $admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'unban-admin@sunnytripstest.com',
        'password' => 'password',
    ]);
    $user = onboardedUser([
        'ban_level' => User::BAN_LEVEL_PERMANENT,
        'banned_at' => now(),
        'ban_reason' => 'Repeated violations.',
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.users.unban', $user->id))
        ->assertRedirect();

    Notification::assertSentTo($user, AccountModerationNotice::class, fn ($notification) => $notification->level === 'restored');
});
