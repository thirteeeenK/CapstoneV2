<?php

use App\Models\AdminModel;
use App\Services\AdminTwoFactorService;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->service = app(AdminTwoFactorService::class);
    $this->google2fa = new Google2FA;
});

function twoFactorAdmin($service, string $email = 'mfa-admin@sunnytripstest.com'): array
{
    $secret = $service->generateSecret();
    $codes = $service->generateRecoveryCodes();

    $admin = AdminModel::create([
        'name' => 'MFA Admin',
        'email' => $email,
        'password' => 'password-secret-123',
        'two_factor_secret' => $service->encryptSecret($secret),
        'two_factor_recovery_codes' => $service->encryptHashedCodes($codes),
        'two_factor_confirmed_at' => now(),
    ]);

    return [$admin, $secret, $codes];
}

function currentTotp($google2fa, $service, string $secret): string
{
    return $google2fa->getCurrentOtp($secret);
}

it('redirects a password-valid login to the challenge when 2FA is enabled', function () {
    [$admin] = twoFactorAdmin($this->service);

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password-secret-123',
    ])->assertRedirect(route('admin.two-factor.challenge'));

    $this->assertGuest('admin');
});

it('logs in directly when 2FA is not yet enabled', function () {
    $admin = AdminModel::create([
        'name' => 'Plain Admin',
        'email' => 'plain-admin@sunnytripstest.com',
        'password' => 'password-secret-123',
    ]);

    // Enforcement lands on the next navigation via EnsureAdminTwoFactor.
    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password-secret-123',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin, 'admin');

    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.two-factor.setup'));
});

it('rejects a wrong password without creating a pending challenge', function () {
    [$admin] = twoFactorAdmin($this->service, 'mfa-wrong-pass@sunnytripstest.com');

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'nope-wrong-password',
    ])->assertSessionHasErrors('email');

    $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
});

it('rejects a wrong TOTP code and stays logged out', function () {
    [$admin] = twoFactorAdmin($this->service, 'mfa-bad-totp@sunnytripstest.com');

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password-secret-123',
    ]);

    $this->post(route('admin.two-factor.challenge.store'), [
        'code' => '000000',
    ])->assertSessionHasErrors('code');

    $this->assertGuest('admin');
});

it('completes login with a valid TOTP code', function () {
    [$admin, $secret] = twoFactorAdmin($this->service, 'mfa-good-totp@sunnytripstest.com');

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password-secret-123',
    ]);

    $this->get(route('admin.two-factor.challenge'))
        ->assertOk()
        ->assertSee('Check your authenticator');

    $this->post(route('admin.two-factor.challenge.store'), [
        'code' => currentTotp($this->google2fa, $this->service, $secret),
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin, 'admin');
});

it('accepts a recovery code exactly once', function () {
    [$admin, , $codes] = twoFactorAdmin($this->service, 'mfa-recovery@sunnytripstest.com');

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password-secret-123',
    ]);

    $this->post(route('admin.two-factor.challenge.store'), [
        'code' => $codes[0],
    ])->assertRedirect(route('admin.dashboard'));

    // Seven codes remain; the used one is consumed.
    $remaining = $this->service->decryptHashedCodes($admin->refresh()->two_factor_recovery_codes);
    expect($remaining)->toHaveCount(7);

    // Sign out, sign back in, reuse the same code — must fail.
    $this->post(route('admin.logout'));

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password-secret-123',
    ]);

    $this->post(route('admin.two-factor.challenge.store'), [
        'code' => $codes[0],
    ])->assertSessionHasErrors('code');

    $this->assertGuest('admin');
});

it('forces an admin without 2FA to the setup page', function () {
    $admin = AdminModel::create([
        'name' => 'Unenrolled Admin',
        'email' => 'unenrolled@sunnytripstest.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.two-factor.setup'));

    $this->actingAs($admin, 'admin')
        ->get(route('admin.two-factor.setup'))
        ->assertOk()
        ->assertSee('Protect your admin account');
});

it('lets an enabled admin reach the dashboard', function () {
    [$admin] = twoFactorAdmin($this->service, 'mfa-dash@sunnytripstest.com');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('enrolls an admin via setup with password and TOTP', function () {
    $admin = AdminModel::create([
        'name' => 'Enrolling Admin',
        'email' => 'enrolling@sunnytripstest.com',
        'password' => 'password-secret-123',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.two-factor.setup'))
        ->assertOk();

    $secret = $this->service->decryptSecret(session('admin_2fa_setup_secret'));
    expect($secret)->not->toBeNull();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.two-factor.confirm'), [
            'password' => 'password-secret-123',
            'code' => currentTotp($this->google2fa, $this->service, $secret),
        ])->assertRedirect(route('admin.two-factor.setup'));

    expect($admin->refresh()->hasEnabledTwoFactor())->toBeTrue();
    expect(session('admin_2fa_codes'))->toHaveCount(8);
});

it('rejects enrollment with a wrong password', function () {
    $admin = AdminModel::create([
        'name' => 'Enroll Fail Admin',
        'email' => 'enroll-fail@sunnytripstest.com',
        'password' => 'password-secret-123',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.two-factor.setup'));

    $secret = $this->service->decryptSecret(session('admin_2fa_setup_secret'));

    $this->actingAs($admin, 'admin')
        ->post(route('admin.two-factor.confirm'), [
            'password' => 'wrong-password',
            'code' => currentTotp($this->google2fa, $this->service, $secret),
        ])->assertSessionHasErrors('password');

    expect($admin->refresh()->hasEnabledTwoFactor())->toBeFalse();
});

it('expires a stale pending challenge', function () {
    [$admin] = twoFactorAdmin($this->service, 'mfa-stale@sunnytripstest.com');

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password-secret-123',
    ]);

    // Backdate the pending marker beyond the 10-minute window.
    session(['admin_2fa_pending_at' => now()->subMinutes(11)->timestamp]);

    $this->post(route('admin.two-factor.challenge.store'), [
        'code' => '123456',
    ])->assertSessionHasErrors('code');

    $this->assertGuest('admin');
});
