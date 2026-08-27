<?php

namespace Tests\Feature\Security;

use App\Models\FailedLoginAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailedLoginLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_customer_login_is_logged_to_database(): void
    {
        $email = 'ghost@sunny.example';

        $this->post(route('login'), [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('failed_login_attempts', [
            'email' => $email,
            'guard' => 'web',
        ]);

        $attempt = FailedLoginAttempt::where('email', $email)->latest()->first();
        $this->assertNotNull($attempt);
        $this->assertNotNull($attempt->ip_address);
        $this->assertNotNull($attempt->attempted_at);
    }

    public function test_failed_admin_login_is_logged_with_admin_guard(): void
    {
        $email = 'ghost-admin@sunny.example';

        $this->post('/admin/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('failed_login_attempts', [
            'email' => $email,
            'guard' => 'admin',
        ]);
    }
}
