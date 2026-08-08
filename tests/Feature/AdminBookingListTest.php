<?php

use App\Models\AdminModel;
use App\Models\Booking;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@sunnytripstest.com',
        'password' => 'password',
    ]);
});

function createAdminListBooking(array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'booking_code' => 'ST-' . date('Y') . '-' . strtoupper(Str::random(5)),
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

it('renders sequential numbers beside booking codes in the list', function () {
    $first = createAdminListBooking(['contact_name' => 'First Guest']);
    $second = createAdminListBooking(['contact_name' => 'Second Guest']);
    $first->created_at = now()->subHours(2);
    $first->save();
    $second->created_at = now()->subHour();
    $second->save();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertSee('First Guest')
        ->assertSee('Second Guest')
        ->assertSee('<td class="py-4 px-6 text-slate-400 font-black">1</td>', false)
        ->assertSee('<td class="py-4 px-6 text-slate-400 font-black">2</td>', false);
});

it('returns the table fragment for AJAX requests filtered by search', function () {
    createAdminListBooking(['contact_name' => 'Juan Dela Cruz', 'booking_code' => 'ST-FIND-ABC01']);
    createAdminListBooking(['contact_name' => 'Maria Santos']);

    $this->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.bookings.index', ['search' => 'ST-FIND']))
        ->assertOk()
        ->assertDontSee('Booking Requests')
        ->assertSee('ST-FIND-ABC01')
        ->assertDontSee('Maria Santos');
});

it('returns the table fragment for AJAX requests filtered by status', function () {
    createAdminListBooking(['contact_name' => 'Paid Guest', 'status' => Booking::STATUS_PAID, 'payment_status' => Booking::PAYMENT_PAID]);
    createAdminListBooking(['contact_name' => 'Pending Guest']);

    $this->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.bookings.index', ['status' => 'paid']))
        ->assertOk()
        ->assertSee('Paid Guest')
        ->assertDontSee('Pending Guest');
});

it('continues sequential numbering across pagination pages', function () {
    foreach (range(1, 17) as $i) {
        createAdminListBooking(['contact_name' => "Guest {$i}"]);
    }

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.bookings.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('<td class="py-4 px-6 text-slate-400 font-black">16</td>', false);
});

it('shows the empty state in the AJAX fragment when nothing matches', function () {
    createAdminListBooking(['contact_name' => 'Juan Dela Cruz']);

    $this->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.bookings.index', ['search' => 'zzzz-no-match']))
        ->assertOk()
        ->assertSee('No bookings found');
});
