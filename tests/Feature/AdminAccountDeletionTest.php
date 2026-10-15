<?php

use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\ChatSession;
use App\Models\SupportInquiry;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\SystemSettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('customer deletion requires an admin and their configured two factor enrollment', function () {
    $user = onboardedUser();
    $this->delete(route('admin.users.destroy', $user->id))->assertRedirect(route('admin.login'));
    $this->actingAs($user)->delete(route('admin.users.destroy', $user->id))->assertRedirect(route('admin.login'));
    $this->actingAs(settingsAdmin(['two_factor_required' => true]), 'admin')->delete(route('admin.users.destroy', $user->id))
        ->assertRedirect(route('admin.two-factor.setup'));
    $this->assertNotSoftDeleted($user);
});

test('scheduled admin deletion overrides the immediate default without extending a deadline', function () {
    $this->freezeTime();
    $admin = settingsAdmin();
    $user = onboardedUser();
    app(SystemSettingsService::class)->save('accounts', ['grace_period' => false], 0, $admin);
    $payload = ['mode' => 'scheduled', 'confirm_email' => $user->email, 'reason' => 'Requested by customer'];
    $this->actingAs($admin, 'admin')->delete(route('admin.users.destroy', $user->id), $payload)
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
    $deadline = User::onlyTrashed()->findOrFail($user->id)->deleted_at->toIso8601String();
    $this->travel(2)->days();
    $this->delete(route('admin.users.destroy', $user->id), $payload)->assertSessionHasNoErrors();
    expect(User::onlyTrashed()->findOrFail($user->id)->deleted_at->toIso8601String())->toBe($deadline);
    $this->get(route('admin.users.index', ['tab' => 'pending']))->assertOk()->assertSee($user->email)->assertSee('Pending deletion');
    $this->get(route('admin.users.show', $user->id))->assertOk()->assertSee('Original deadline');
    $this->get(route('admin.users.index', ['tab' => 'pending']), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('listings-results')->assertSee($user->email);
});

test('permanent deletion removes access and preferences while retaining booking history and a safe audit', function () {
    $user = onboardedUser(['remember_token' => 'secret-remember']);
    $admin = settingsAdmin();
    UserPreference::create(['user_id' => $user->id]);
    $booking = Booking::factory()->paid()->create(['user_id' => $user->id]);
    $session = ChatSession::create(['user_id' => $user->id, 'session_token' => 'old-private-chat-token']);
    $inquiry = SupportInquiry::create([
        'ticket_number' => 'TKT-DELETE', 'chat_session_id' => $session->id, 'user_id' => $user->id,
        'status' => SupportInquiry::STATUS_PENDING, 'requested_at' => now(),
    ]);
    DB::table('sessions')->insert(['id' => 'old-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset-secret', 'created_at' => now()]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(), 'type' => 'test',
        'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id, 'data' => '{}',
    ]);

    $this->actingAs($admin, 'admin')->delete(route('admin.users.destroy', $user->id), [
        'mode' => 'permanent', 'confirm_email' => $user->email, 'reason' => 'Reset test account',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('user_preferences', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $user->id, 'notifiable_type' => $user->getMorphClass()]);
    expect($booking->refresh()->user_id)->toBeNull();
    expect($booking->payment_status)->toBe(Booking::PAYMENT_PAID);
    expect($session->refresh()->session_token)->not->toBe('old-private-chat-token');
    expect($inquiry->refresh()->status)->toBe(SupportInquiry::STATUS_RESOLVED);
    expect($inquiry->user_id)->toBeNull();
    $log = AdminAuditLog::where('admin_id', $admin->id)->sole();
    expect($log->new_values['action'])->toBe('account_permanently_deleted');
    expect($log->new_values['reason'])->toBe('Reset test account');
    expect(json_encode([$log->old_values, $log->new_values]))->not->toContain($user->password, 'secret-remember');
    $this->get(route('admin.users.index'))->assertOk()->assertDontSee($user->email);
});

test('wrong confirmation email or mode does not delete a customer', function (array $payload, string $error) {
    $user = onboardedUser(['email' => 'delete-me@example.com']);
    $this->actingAs(settingsAdmin(), 'admin')->delete(route('admin.users.destroy', $user->id), $payload)
        ->assertSessionHasErrors($error);
    $this->assertNotSoftDeleted($user);
    $this->assertDatabaseCount('admin_audit_logs', 0);
})->with([
    'wrong email' => [['mode' => 'permanent', 'confirm_email' => 'other@example.com', 'reason' => 'Testing'], 'confirm_email'],
    'invalid mode' => [['mode' => 'wipe', 'confirm_email' => 'delete-me@example.com', 'reason' => 'Testing'], 'mode'],
    'missing reason' => [['mode' => 'permanent', 'confirm_email' => 'delete-me@example.com'], 'reason'],
]);

test('an admin can purge a pending customer and stale repeat requests fail cleanly', function () {
    $user = User::factory()->create(['deleted_at' => now()->subDay()]);
    $payload = ['mode' => 'permanent', 'confirm_email' => $user->email, 'reason' => 'Test cleanup'];
    $this->actingAs(settingsAdmin(), 'admin')->delete(route('admin.users.destroy', $user->id), $payload)->assertSessionHasNoErrors();
    $this->delete(route('admin.users.destroy', $user->id), $payload)->assertNotFound();
});

test('self service deletion uses the saved admin policy and matching messages', function () {
    app(SystemSettingsService::class)->save('accounts', ['grace_period' => false], 0, settingsAdmin());
    $user = onboardedUser();
    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('deleted immediately');
    $this->delete('/profile', ['password' => 'wrong'])->assertSessionHasErrorsIn('userDeletion', 'password');
    $this->assertNotSoftDeleted($user);
    $this->delete('/profile', ['password' => 'password'])->assertRedirect('/')->assertSessionHas('account_permanently_deleted', true);
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->get('/')->assertOk()->assertSee('Account permanently deleted')->assertDontSee('Log in to cancel');
});

test('customer deletion preserves admin-only sessions with overlapping account IDs', function (bool $encrypted) {
    $admin = settingsAdmin();
    $user = onboardedUser();
    config(['session.encrypt' => $encrypted]);
    $payload = serialize([auth('admin')->getName() => $admin->id]);
    if ($encrypted) {
        $payload = Crypt::encrypt($payload);
    }
    DB::table('sessions')->insert([
        'id' => 'staff-only-device', 'user_id' => $user->id, 'payload' => base64_encode($payload), 'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin, 'admin')->delete(route('admin.users.destroy', $user->id), [
        'mode' => 'permanent', 'confirm_email' => $user->email, 'reason' => 'Test cleanup',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sessions', ['id' => 'staff-only-device']);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
})->with(['plain sessions' => false, 'encrypted sessions' => true]);
