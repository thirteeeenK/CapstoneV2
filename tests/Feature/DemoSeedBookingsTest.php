<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

test('registration creates no seed bookings when demo mode off', function () {
    Config::set('app.demo_mode', false);

    $this->post('/register', [
        'name' => 'No Seed',
        'email' => 'noseed@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '09123456789',
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $user = User::where('email', 'noseed@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->bookings()->count())->toBe(0);
});
