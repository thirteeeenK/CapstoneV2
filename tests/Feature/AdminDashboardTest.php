<?php

use App\Models\AdminModel;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();

    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'dashboard-admin@sunnytripstest.com',
        'password' => 'password',
    ]);
});

function createDashboardBooking(array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'booking_code' => 'ST-'.date('Y').'-'.strtoupper(Str::random(5)),
        'user_id' => null,
        'status' => Booking::STATUS_PENDING,
        'total_amount' => 1000.00,
        'discount_amount' => 0.00,
        'tax_amount' => 0.00,
        'net_amount' => 1000.00,
        'payment_status' => Booking::PAYMENT_UNPAID,
        'contact_name' => 'Test Guest',
        'contact_email' => 'guest@example.com',
        'contact_phone' => '09170000000',
        'guest_manifest' => null,
    ], $overrides));
}

it('renders the dashboard with all sections for an authenticated admin', function () {
    createDashboardBooking(['status' => Booking::STATUS_PAID, 'payment_status' => Booking::PAYMENT_PAID, 'net_amount' => 3000.00]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Needs attention')
        ->assertSee('Business performance')
        ->assertSee('chart-trend')
        ->assertSee('chart-funnel')
        ->assertSee('chart-topsellers')
        ->assertSee('Recent activity')
        ->assertSee('AI Insights');
});

it('redirects guests to the admin login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

it('flags stale pending bookings in AI Insights', function () {
    $booking = createDashboardBooking();
    $booking->created_at = now()->subDays(3);
    $booking->save();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('stale over 48h');
});

it('shows the all-clear state when there is nothing to act on', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('All clear');
});

it('caches only scalars and arrays so serializing drivers never unserialize models', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();

    $cached = Cache::get('admin.dashboard.v2');
    $this->assertIsArray($cached);

    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($cached));
    foreach ($iterator as $value) {
        $this->assertTrue(
            is_scalar($value) || $value === null || is_array($value),
            'Cached dashboard payload must not contain objects'
        );
    }
});
