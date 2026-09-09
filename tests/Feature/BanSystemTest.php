<?php

use App\Models\AdminModel;
use App\Models\User;
use App\Services\GeminiService;

beforeEach(function () {
    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@sunnytripstest.com',
        'password' => 'password',
    ]);
});

function createBannedUser(array $ban = []): User
{
    return User::factory()->create(array_merge([
        'ban_level' => User::BAN_LEVEL_PERMANENT,
        'banned_at' => now(),
        'ban_reason' => 'Repeated violations',
    ], $ban));
}

it('lets an admin issue a warning that does not restrict access', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.ban', $user->id), [
            'ban_level' => 'warning',
            'ban_reason' => 'First offense — please review the rules.',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->isWarned())->toBeTrue()
        ->and($user->isBanned())->toBeFalse()
        ->and($user->banned_at)->not->toBeNull()
        ->and($user->ban_expires_at)->toBeNull();
});

it('still lets a warned user log in', function () {
    $user = User::factory()->create();
    $user->update(['ban_level' => 'warning', 'banned_at' => now()]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated('web');
});

it('lets an admin issue a temporary ban with an expiry', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.ban', $user->id), [
            'ban_level' => 'temporary',
            'ban_duration_days' => 7,
            'ban_reason' => 'Spamming the chatbot.',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->isTemporarilyBanned())->toBeTrue()
        ->and($user->isBanned())->toBeTrue()
        ->and($user->ban_expires_at)->not->toBeNull()
        ->and($user->ban_expires_at->greaterThan(now()->addDays(6)))->toBeTrue();
});

it('rejects login for a temporarily banned user and sends them to the suspended page', function () {
    $user = createBannedUser([
        'ban_level' => User::BAN_LEVEL_TEMPORARY,
        'ban_expires_at' => now()->addDays(3),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.suspended'));

    expect(session('suspended')['message'])->toContain('temporarily suspended');
    $this->assertGuest('web');
});

it('rejects login for a permanently banned user and sends them to the suspended page', function () {
    $user = createBannedUser(['ban_reason' => 'Terms of service violation']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.suspended'));

    expect(session('suspended')['message'])->toContain('permanently banned')
        ->and(session('suspended')['message'])->toContain('Terms of service violation');
    $this->assertGuest('web');
});

it('logs out an actively logged-in user once they are banned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update([
        'ban_level' => User::BAN_LEVEL_PERMANENT,
        'banned_at' => now(),
        'ban_reason' => 'Abuse',
    ]);

    $this->get('/profile')
        ->assertRedirect(route('account.suspended'))
        ->assertSessionHas('suspended');

    expect(session('suspended')['message'])->toContain('permanently banned');
    $this->assertGuest('web');
});

it('renders the account-suspended page with the ban notice inline', function () {
    $user = createBannedUser(['ban_reason' => 'Spam']);

    $this->withSession(['suspended' => $user->banNotice()])
        ->get(route('account.suspended'))
        ->assertOk()
        ->assertSee('SUS · Account Restricted')
        ->assertSee('Your account has been suspended')
        ->assertSee('permanently banned')
        ->assertSee('Contact Support')
        ->assertSee('Spam')
        ->assertDontSee('I understand');
});

it('lets a logged-in banned user view the suspended page without a redirect loop', function () {
    $user = createBannedUser();
    $this->actingAs($user);

    $this->get(route('account.suspended'))
        ->assertOk()
        ->assertSee('Account Restricted');
});

it('restores login once a temporary ban expires', function () {
    $user = createBannedUser([
        'ban_level' => User::BAN_LEVEL_TEMPORARY,
        'ban_expires_at' => now()->addDays(1),
    ]);

    expect($user->isBanned())->toBeTrue();

    $user->update(['ban_expires_at' => now()->subDay()]);
    $user->refresh();

    expect($user->isBanned())->toBeFalse();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated('web');
});

it('lets an admin unban a user and clears all ban fields', function () {
    $user = createBannedUser();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.unban', $user->id))
        ->assertRedirect();

    $user->refresh();

    expect($user->ban_level)->toBeNull()
        ->and($user->banned_at)->toBeNull()
        ->and($user->ban_expires_at)->toBeNull()
        ->and($user->ban_reason)->toBeNull()
        ->and($user->isBanned())->toBeFalse();
});

it('filters users by warned, temporary, and permanent tabs', function () {
    $warned = User::factory()->create(['ban_level' => 'warning', 'banned_at' => now()]);
    $temporary = createBannedUser([
        'ban_level' => User::BAN_LEVEL_TEMPORARY,
        'ban_expires_at' => now()->addDays(3),
    ]);
    $permanent = createBannedUser();
    $active = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['tab' => 'warned']))
        ->assertOk()
        ->assertSee($warned->name)
        ->assertDontSee($temporary->name)
        ->assertDontSee($permanent->name)
        ->assertDontSee($active->name);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['tab' => 'temporary']))
        ->assertOk()
        ->assertSee($temporary->name)
        ->assertDontSee($warned->name)
        ->assertDontSee($permanent->name)
        ->assertDontSee($active->name);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['tab' => 'permanent']))
        ->assertOk()
        ->assertSee($permanent->name)
        ->assertDontSee($warned->name)
        ->assertDontSee($temporary->name)
        ->assertDontSee($active->name);
});

it('validates the level, reason, and duration when banning', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.ban', $user->id), [
            'ban_level' => 'permanent',
            'ban_reason' => '',
        ])
        ->assertSessionHasErrors('ban_reason');

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.ban', $user->id), [
            'ban_level' => 'temporary',
            'ban_reason' => 'Abuse',
        ])
        ->assertSessionHasErrors('ban_duration_days');

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.ban', $user->id), [
            'ban_level' => 'nonsense',
        ])
        ->assertSessionHasErrors('ban_level');
});

it('keeps the chatbot guard blocking banned users', function () {
    $service = app(GeminiService::class);

    $banned = createBannedUser(['ban_reason' => 'Spam']);
    $result = $service->detectAbuseAndGuard($banned, 'hello');

    expect($result['blocked'])->toBeTrue()
        ->and($result['response'])->toContain('Spam');

    $clean = User::factory()->create();
    expect($service->detectAbuseAndGuard($clean, 'hello'))->toBeNull();
});
