<?php

test('profile page is displayed', function () {
    $user = onboardedUser();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = onboardedUser();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email changes require the correct password without saving any partial changes', function (mixed $password) {
    $user = onboardedUser();
    $this->actingAs($user)->patch('/profile', [
        'name' => 'Unapproved name', 'email' => 'changed@example.com', 'current_password' => $password,
    ])->assertSessionHasErrors('current_password');
    expect($user->fresh()->email)->toBe($user->email)
        ->and($user->fresh()->name)->toBe($user->name);
})->with([null, 'wrong-password']);

test('name only edits need no password and ignore credential fields', function () {
    $user = onboardedUser();
    $hash = $user->password;
    $this->actingAs($user)->patch('/profile', [
        'name' => 'New name', 'email' => $user->email, 'password' => 'unwanted-password',
        'google_id' => 'unwanted-id', 'current_password' => 'incorrect-but-not-needed',
    ])->assertSessionHasNoErrors();
    expect($user->fresh()->name)->toBe('New name')->and($user->fresh()->password)->toBe($hash)
        ->and($user->fresh()->google_id)->toBeNull();
});

test('google only accounts cannot change email through a direct profile request', function () {
    $user = onboardedUser(['password' => null, 'google_id' => 'linked-google']);
    $this->actingAs($user)->patch('/profile', ['name' => 'Changed', 'email' => 'changed@example.com'])
        ->assertSessionHasErrors('confirmation');
    expect($user->fresh()->email)->toBe($user->email);
});

test('email change password guesses are limited per account', function () {
    $user = onboardedUser();
    $this->actingAs($user);
    for ($i = 0; $i < 5; $i++) {
        $this->patchJson('/profile', ['name' => 'Name', 'email' => 'changed@example.com'])->assertUnprocessable();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
        ->patchJson('/profile', ['name' => 'Name', 'email' => 'changed@example.com'])
        ->assertTooManyRequests()->assertHeader('Retry-After');
    $this->patch('/profile', ['name' => 'Allowed name', 'email' => $user->email])->assertSessionHasNoErrors();
});

test('malformed email input fails validation without crashing', function () {
    $user = onboardedUser();
    $this->actingAs($user)->patchJson('/profile', ['name' => 'Name', 'email' => ['bad']])->assertUnprocessable();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = onboardedUser();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can schedule their account deletion', function () {
    $user = onboardedUser();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertSoftDeleted($user);
    $response->assertSessionHas('account_deletion_requested', true);
});

test('correct password must be provided to delete account', function () {
    $user = onboardedUser();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
