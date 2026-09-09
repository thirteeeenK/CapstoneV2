<?php

use App\Models\AdminModel;
use App\Models\User;
use Illuminate\Support\Facades\Http;

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
