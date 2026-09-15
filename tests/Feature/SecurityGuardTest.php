<?php

use App\Models\AdminAuditLog;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\FailedLoginAttempt;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('stores passwords hashed, never as plaintext', function () {
    $user = onboardedUser(['password' => 'super-secret-pass']);

    expect($user->refresh()->password)->not->toBe('super-secret-pass')
        ->toStartWith('$2y$');
});

it('hides a booking from other users with a 404 while the owner and an admin can view it', function () {
    $owner = onboardedUser();
    $intruder = onboardedUser();
    $admin = AdminModel::create([
        'name' => 'Audit Admin',
        'email' => 'security-admin@test.com',
        'password' => 'password',
    ]);
    $booking = Booking::factory()->approved()->create(['user_id' => $owner->id]);

    $r1 = $this->actingAs($intruder)->get(route('booking.show', $booking->booking_code));
    $r2 = $this->actingAs($owner)->get(route('booking.show', $booking->booking_code));
    $this->actingAs($owner);
    $this->app['auth']->guard('admin')->setUser($admin);
    $r3 = $this->get(route('booking.show', $booking->booking_code));

    $r1->assertNotFound();
    $r2->assertOk();
    $r3->assertOk();
});

it('redirects guests to login for booking and checkout pages', function () {
    $this->get(route('booking.index'))->assertRedirect(route('login'));
    $this->get(route('checkout.index'))->assertRedirect(route('login'));
});

it('blocks regular users from the admin dashboard', function () {
    $user = onboardedUser();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

it('records a failed login attempt with email and IP address', function () {
    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'intruder@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

    $attempt = FailedLoginAttempt::where('email', 'intruder@example.com')->first();

    expect($attempt)->not->toBeNull()
        ->and($attempt->ip_address)->toBe(request()->ip())
        ->and($attempt->guard)->toBe('web');
});

it('writes an admin audit log entry when an admin creates a package', function () {
    Http::fake();
    $admin = AdminModel::create([
        'name' => 'Audit Admin',
        'email' => 'audit-admin@test.com',
        'password' => 'password',
    ]);
    $destination = DestinationModel::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.packages.store'), [
            'destination_id' => $destination->id,
            'name' => 'Security Test Package',
            'price' => 4999.99,
            'min_pax' => 2,
            'is_active' => true,
            'inclusions_text' => 'Hotel stay, transfers',
        ])->assertRedirect(route('admin.packages.index'));

    $package = Package::where('name', 'Security Test Package')->first();
    $log = AdminAuditLog::where('auditable_id', $package->id)
        ->where('auditable_type', $package->getMorphClass())
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->admin_id)->toBe($admin->id)
        ->and($log->new_values)->toHaveKey('name', 'Security Test Package');
});

it('returns a JSON response without executable script when the chat receives a script payload', function () {
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => [['parts' => [['text' => 'Safe reply about island tours.']]]]]]]),
    ]);
    $user = onboardedUser();

    $response = $this->actingAs($user)->postJson('/chat', [
        'message' => '<script>alert("xss")</script> Show me rooms',
    ]);

    $response->assertOk();
    expect($response->getContent())->not->toContain('<script>');
});

it('rejects chat messages longer than 1000 characters', function () {
    $user = onboardedUser();

    $this->actingAs($user)->postJson('/chat', [
        'message' => str_repeat('a', 1001),
    ])->assertJsonValidationErrors('message');

    expect(ChatMessage::count())->toBe(0);
});

it('persists an authenticated chat message linked to the user session', function () {
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => [['parts' => [['text' => 'Here is a recommendation for you!']]]]]]]),
    ]);
    $user = onboardedUser();

    $this->actingAs($user)->postJson('/chat', [
        'message' => 'Recommend a hotel in Boracay',
    ])->assertOk();

    $session = ChatSession::where('user_id', $user->id)->first();

    expect($session)->not->toBeNull()
        ->and($session->messages)->toHaveCount(2)
        ->and($session->messages->pluck('message'))->toContain('Recommend a hotel in Boracay');
});

it('writes a status history entry when a booking transitions', function () {
    Http::fake();
    $user = onboardedUser();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
    ]);

    $this->actingAs($user)->post(route('booking.cancel', $booking->booking_code), [
        'reason' => 'Plans changed, need to cancel this security test booking.',
    ])->assertRedirect();

    expect($booking->history()->where('to_status', Booking::STATUS_CANCELLATION_REQUESTED)->exists())->toBeTrue();
});
