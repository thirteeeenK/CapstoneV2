<?php

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;

test('login within five days restores the account and shows recovery once', function () {
    $this->freezeTime();
    $user = onboardedUser(['deleted_at' => now()->subDays(5)->addSecond()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('account_recovered', true);

    $this->assertAuthenticatedAs($user);
    $this->assertNotSoftDeleted($user);
    $this->get('/profile')->assertOk()->assertSee('Account recovered');
    $this->get('/profile')->assertOk()->assertDontSee('account-recovered-title');
});

test('expired accounts cannot recover even before cleanup runs', function (int $secondsPastDeadline) {
    $this->freezeTime();
    $user = User::factory()->create(['deleted_at' => now()->subDays(5)->subSeconds($secondsPastDeadline)]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertSoftDeleted($user);
})->with([0, 1, 86400]);

test('incorrect credentials do not cancel pending deletion', function () {
    $user = User::factory()->create(['deleted_at' => now()->subDay()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertSoftDeleted($user);
});

test('banned accounts cannot cancel pending deletion through login', function () {
    $user = User::factory()->create([
        'deleted_at' => now()->subDay(),
        'ban_level' => User::BAN_LEVEL_PERMANENT,
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('account.suspended'));

    $this->assertGuest();
    $this->assertSoftDeleted($user);
});

test('cleanup permanently deletes only accounts whose five days have elapsed', function () {
    $this->freezeTime();
    $expired = User::factory()->create(['deleted_at' => now()->subDays(5)]);
    $pending = User::factory()->create(['deleted_at' => now()->subDays(5)->addSecond()]);
    $active = User::factory()->create();
    UserPreference::create(['user_id' => $expired->id]);

    $this->artisan('accounts:delete-expired')->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $expired->id]);
    $this->assertDatabaseMissing('user_preferences', ['user_id' => $expired->id]);
    $this->assertSoftDeleted($pending);
    $this->assertNotSoftDeleted($active);
    $this->artisan('accounts:delete-expired')->assertSuccessful();
});

test('requesting deletion revokes remembered access and existing sessions', function () {
    $user = onboardedUser(['remember_token' => 'old-token']);
    DB::table('sessions')->insert([
        'id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    $this->assertGuest();
    $this->assertSoftDeleted($user);
    expect(User::withTrashed()->find($user->id)->remember_token)->not->toBe('old-token');
    $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
});

test('a recovered account survives its original deletion deadline', function () {
    $this->freezeTime();
    $user = User::factory()->create(['deleted_at' => now()->subDays(4)]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $this->travel(2)->days();
    $this->artisan('accounts:delete-expired')->assertSuccessful();

    $this->assertNotSoftDeleted($user);
});
