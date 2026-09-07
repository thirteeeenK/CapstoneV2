<?php

use App\Models\AdminModel;
use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::fake([
        '*generateContent*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'not-valid-json']]]],
            ],
        ]),
    ]);

    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@sunnytripstest.com',
        'password' => 'password',
    ]);
});

function createReportBooking(array $overrides = []): Booking
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
        'created_at' => now()->subDays(5),
    ], $overrides));
}

it('renders the report page with KPI values for the default range', function () {
    createReportBooking(['status' => Booking::STATUS_PAID, 'payment_status' => Booking::PAYMENT_PAID, 'net_amount' => 3000.00]);
    createReportBooking(['status' => Booking::STATUS_APPROVED, 'net_amount' => 2000.00]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Booking Reports')
        ->assertSee('Total Bookings')
        ->assertSee('Revenue')
        ->assertSee('Daily Breakdown')
        ->assertSee('3,000.00')
        ->assertSee('2,000.00');
});

it('narrows the report by date range and status filter', function () {
    createReportBooking(['status' => Booking::STATUS_PAID, 'payment_status' => Booking::PAYMENT_PAID, 'net_amount' => 3000.00, 'created_at' => now()->subDays(2)]);
    createReportBooking(['status' => Booking::STATUS_EXPIRED, 'net_amount' => 1500.00, 'created_at' => now()->subDays(90)]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reports.index', ['status' => 'paid']))
        ->assertOk()
        ->assertSee('3,000.00')
        ->assertDontSee('1,500.00');
});

it('downloads the report as a PDF', function () {
    createReportBooking(['status' => Booking::STATUS_PAID, 'payment_status' => Booking::PAYMENT_PAID, 'net_amount' => 3000.00]);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reports.export-pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeaderContains('Content-Disposition', 'attachment');

    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

it('returns an AI analysis fragment with the offline fallback when no API key is set', function () {
    createReportBooking(['status' => Booking::STATUS_PENDING, 'created_at' => now()->subHours(60)]);
    createReportBooking(['status' => Booking::STATUS_EXPIRED]);

    $this->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post(route('admin.reports.analyze'), [
            'from' => now()->subDays(30)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'status' => '',
        ])
        ->assertOk()
        ->assertSee('Executive Summary')
        ->assertSee('Insights')
        ->assertSee('Anomalies & Risks', false)
        ->assertSee('Recommendations')
        ->assertDontSee('Booking Reports');
});

it('paginates booking details at 50 per page', function () {
    foreach (range(1, 55) as $i) {
        createReportBooking(['created_at' => now()->subDays(2)]);
    }

    $pageOne = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reports.index'))
        ->assertOk();

    $bookings = $pageOne->viewData('bookings');

    expect($bookings->perPage())->toBe(50)
        ->and($bookings->total())->toBe(55)
        ->and($bookings->count())->toBe(50);

    $pageTwo = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reports.index', ['page' => 2]))
        ->assertOk();

    expect($pageTwo->viewData('bookings')->count())->toBe(5);
});

it('redirects back with the analysis for non-AJAX submissions', function () {
    createReportBooking(['status' => Booking::STATUS_EXPIRED]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reports.analyze'), [
            'from' => now()->subDays(30)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'status' => '',
        ])
        ->assertRedirect(route('admin.reports.index', [
            'from' => now()->subDays(30)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]));

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Executive Summary');
});
