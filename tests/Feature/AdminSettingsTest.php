<?php

use App\Models\AdminAuditLog;
use App\Services\SystemSettingsService;

test('settings require admin authentication and existing two factor enrollment', function () {
    $this->get('/admin/settings')->assertRedirect(route('admin.login'));
    $this->actingAs(onboardedUser())->patch('/admin/settings/accounts', [])->assertRedirect(route('admin.login'));
    $this->actingAs(settingsAdmin(['two_factor_required' => true]), 'admin')
        ->get('/admin/settings')->assertRedirect(route('admin.two-factor.setup'));
});

test('an admin can view all five settings forms', function () {
    $this->actingAs(settingsAdmin(), 'admin')->get('/admin/settings')
        ->assertOk()->assertSee('Business information')->assertSee('Support availability')
        ->assertSee('Guest chatbot limits')->assertSee('Admin email notifications')->assertSee('Account deletion');
});

test('settings is the final sidebar destination and includes security controls', function () {
    $response = $this->actingAs(settingsAdmin(), 'admin')->get(route('admin.settings.index'))
        ->assertOk()->assertSee('Security &amp; 2FA', false)->assertSee('Turn on two-factor');

    preg_match('/<aside\b[^>]*>(.*?)<\/aside>/s', $response->getContent(), $sidebar);
    preg_match_all('/<a\b[^>]*href="([^"]*)"/s', $sidebar[1], $links);
    expect(end($links[1]))->toBe(route('admin.settings.index'));
    expect($sidebar[1])->not->toContain(route('admin.two-factor.setup'));
    $response->assertSee('id="security"', false)->assertSee('name="return_to_settings" value="1"', false);
});

test('saving a false policy overrides the environment and records the admin', function () {
    $admin = settingsAdmin();
    config(['auth.account_deletion_grace_period' => true]);
    $this->actingAs($admin, 'admin')->patch('/admin/settings/accounts', [
        'section' => 'accounts', 'revision' => 0, 'values' => ['grace_period' => '0'],
    ])->assertRedirect(route('admin.settings.index').'#accounts')->assertSessionHasNoErrors();

    expect(app(SystemSettingsService::class)->gracePeriodEnabled())->toBeFalse();
    $this->assertDatabaseHas('system_settings', ['section' => 'accounts', 'revision' => 1, 'updated_by' => $admin->id]);
    $log = AdminAuditLog::sole();
    expect($log->new_values['action'])->toBe('settings_updated');
    expect($log->new_values['values']['grace_period'])->toBeFalse();
    expect($log->old_values['values']['grace_period'])->toBeTrue();
    $this->assertDatabaseCount('system_settings', 1);
});

test('unsaved account settings use the environment fallback', function () {
    config(['auth.account_deletion_grace_period' => false]);
    expect(app(SystemSettingsService::class)->gracePeriodEnabled())->toBeFalse();
    config(['auth.account_deletion_grace_period' => true]);
    expect(app(SystemSettingsService::class)->gracePeriodEnabled())->toBeTrue();
});

test('stale saves preserve the newer policy and unrelated sections', function () {
    $admin = settingsAdmin();
    $settings = app(SystemSettingsService::class);
    $settings->save('accounts', ['grace_period' => false], 0, $admin);
    $settings->save('chatbot', ['daily_limit' => 25, 'minute_limit' => 3], 0, $admin);

    $this->actingAs($admin, 'admin')->patch('/admin/settings/accounts', [
        'section' => 'accounts', 'revision' => 0, 'values' => ['grace_period' => true],
    ])->assertSessionHasErrors('revision');

    expect($settings->gracePeriodEnabled())->toBeFalse();
    expect($settings->section('chatbot')['daily_limit'])->toBe(25);
    $this->assertDatabaseCount('admin_audit_logs', 2);
});

test('settings reject unknown values and invalid limits', function (array $values, string $error) {
    $this->actingAs(settingsAdmin(), 'admin')->patch('/admin/settings/chatbot', [
        'section' => 'chatbot', 'revision' => 0, 'values' => $values,
    ])->assertSessionHasErrors($error);
    $this->assertDatabaseCount('system_settings', 0);
})->with([
    'unbounded daily' => [['daily_limit' => 1001, 'minute_limit' => 5], 'values.daily_limit'],
    'zero burst' => [['daily_limit' => 15, 'minute_limit' => 0], 'values.minute_limit'],
    'unknown setting' => [['daily_limit' => 15, 'minute_limit' => 5, 'secret' => 'value'], 'values'],
]);

test('business data is escaped and unsafe social URLs are rejected', function () {
    $admin = settingsAdmin();
    $business = config('system_settings.business');
    $business['facebook_url'] = 'javascript:alert(1)';
    $this->actingAs($admin, 'admin')->patch('/admin/settings/business', [
        'section' => 'business', 'revision' => 0, 'values' => $business,
    ])->assertSessionHasErrors('values.facebook_url');
    $business['facebook_url'] = 'https://facebook.com/sunnytrips';
    $business['display_name'] = '<script>alert(1)</script>';
    app(SystemSettingsService::class)->save('business', $business, 0, $admin);
    $this->app['auth']->shouldUse('web');
    $this->get('/')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('selected email recipients and office hours must be valid', function () {
    $this->actingAs(settingsAdmin(), 'admin');
    $notifications = config('system_settings.notifications');
    $notifications['recipient_mode'] = 'selected';
    $this->patch('/admin/settings/notifications', [
        'section' => 'notifications', 'revision' => 0, 'values' => $notifications,
    ])->assertSessionHasErrors('values.admin_ids');
    $support = config('system_settings.support');
    $support['schedule']['mon']['closes'] = '07:00';
    $this->patch('/admin/settings/support', [
        'section' => 'support', 'revision' => 0, 'values' => $support,
    ])->assertSessionHasErrors('values.schedule.mon.closes');
    $this->assertDatabaseCount('system_settings', 0);
});
