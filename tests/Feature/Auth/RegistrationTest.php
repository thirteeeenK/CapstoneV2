<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register with consent', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '09123456789',
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'terms_version' => config('legal.documents.terms.version'),
        'privacy_version' => config('legal.documents.privacy.version'),
        'ai_disclosure_version' => config('legal.documents.ai_disclosure.version'),
    ]);

    $response->assertRedirect(route('onboarding.index', absolute: false));
    $response->assertSessionHas('success');
    $this->assertAuthenticated();
});

test('registration fails without all consent checkboxes', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '09123456789',
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        // privacy and AI disclosure missing
    ]);

    $response->assertSessionHasErrors(['privacy_accepted', 'ai_disclosure_accepted']);
});

test('registration fails without age confirmation', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '09123456789',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $response->assertSessionHasErrors('age_confirmed');
});

test('registration fails with invalid philippine phone number', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '12345678901', // invalid
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $response->assertSessionHasErrors('phone_number');
});

test('registration records consent audit trail', function () {
    $this->post('/register', [
        'name' => 'Audit User',
        'email' => 'audit@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '09123456789',
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'audit@example.com',
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
        'ai_disclosure_accepted_at' => now(),
    ]);
});
