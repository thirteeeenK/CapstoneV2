<?php

use App\Models\AdminModel;
use App\Models\Booking;
use App\Notifications\BookingDocumentAdded;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('public');

    $this->admin = AdminModel::create([
        'name' => 'Docs Admin',
        'email' => 'docs-admin@test.com',
        'password' => 'password',
    ]);

    $this->user = onboardedUser();
});

function docUploadPayload(array $overrides = []): array
{
    return array_merge([
        'kind' => 'ticket',
        'label' => 'Airline e-ticket — outbound',
        'file' => UploadedFile::fake()->create('ticket.png', 100, 'image/png'),
    ], $overrides);
}

it('lets admin upload a document to a pending booking and notifies the user', function () {
    $booking = Booking::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.attachments.store', $booking->id), docUploadPayload())
        ->assertRedirect();

    $attachment = $booking->fresh()->attachments()->first();
    expect($attachment)->not->toBeNull()
        ->and($attachment->kind)->toBe('ticket')
        ->and(Storage::disk('public')->exists($attachment->path))->toBeTrue();

    Notification::assertSentTo($this->user, BookingDocumentAdded::class);
});

it('allows uploads after payment without touching status or totals', function () {
    $booking = Booking::factory()->paid()->create(['user_id' => $this->user->id]);
    $net = $booking->net_amount;

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.attachments.store', $booking->id), docUploadPayload())
        ->assertRedirect();

    $fresh = $booking->fresh();
    expect($fresh->status)->toBe(Booking::STATUS_PAID)
        ->and((float) $fresh->net_amount)->toBe((float) $net)
        ->and($fresh->attachments()->count())->toBe(1);
});

it('rejects non-image non-pdf uploads', function () {
    $booking = Booking::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.attachments.store', $booking->id), docUploadPayload([
            'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
        ]))
        ->assertSessionHasErrors('file');

    expect($booking->fresh()->attachments()->count())->toBe(0);
});

it('blocks uploads on closed bookings', function () {
    $booking = Booking::factory()->create([
        'user_id' => $this->user->id,
        'status' => Booking::STATUS_CANCELLED,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.attachments.store', $booking->id), docUploadPayload())
        ->assertRedirect();

    expect($booking->fresh()->attachments()->count())->toBe(0);
});

it('shows uploaded documents on the user booking page', function () {
    $booking = Booking::factory()->paid()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.attachments.store', $booking->id), docUploadPayload());

    $this->actingAs($this->user)
        ->get(route('booking.show', $booking->booking_code))
        ->assertOk()
        ->assertSee('Travel Documents')
        ->assertSee('Airline e-ticket — outbound');
});

it('lets admin delete a document and removes the file', function () {
    $booking = Booking::factory()->paid()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.attachments.store', $booking->id), docUploadPayload());

    $attachment = $booking->fresh()->attachments()->first();
    $path = $attachment->path;

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.bookings.attachments.destroy', [$booking->id, $attachment->id]))
        ->assertRedirect();

    expect($booking->fresh()->attachments()->count())->toBe(0)
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});
