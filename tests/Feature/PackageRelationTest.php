<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->partialMock(GeminiService::class, function ($mock) {
        $mock->shouldReceive('generateEmbedding')->andReturnNull();
    });

    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@sunnytripstest.com',
        'password' => 'password',
    ]);

    $this->destination = DestinationModel::firstOrCreate(
        ['name' => 'Boracay'],
        ['description' => 'Island destination', 'is_shown' => true]
    );

    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Package Link Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
    ]);

    $this->room = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_name' => 'Package Link Suite',
        'base_price' => 5000.00,
    ]);

    $this->activity = ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Package Link Cruise',
    ]);

    $this->addOn = AddOnModel::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Package Link Transfer',
    ]);
});

function validPackagePayload(object $ctx, array $overrides = []): array
{
    return array_merge([
        'destination_id' => $ctx->destination->id,
        'name' => 'Linked Promo Package',
        'type' => 'Hotel + Tour',
        'price' => 7999.00,
        'days' => 3,
        'nights' => 2,
        'min_pax' => 2,
        'is_active' => 1,
        'inclusions_text' => "Roundtrip Airfare\nTerminal Fee",
        'hotel_ids' => [$ctx->hotel->id],
        'room_ids' => [$ctx->room->id],
        'activity_ids' => [$ctx->activity->id],
        'add_on_ids' => [$ctx->addOn->id],
    ], $overrides);
}

it('stores a package with linked hotel, room, activity and add-on while keeping the manual price', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.packages.store'), validPackagePayload($this))
        ->assertRedirect(route('admin.packages.index'));

    $package = Package::where('name', 'Linked Promo Package')->firstOrFail();

    expect((float) $package->price)->toBe(7999.00)
        ->and($package->hotels->pluck('id')->all())->toBe([$this->hotel->id])
        ->and($package->rooms->pluck('id')->all())->toBe([$this->room->id])
        ->and($package->activities->pluck('id')->all())->toBe([$this->activity->id])
        ->and($package->addOns->pluck('id')->all())->toBe([$this->addOn->id])
        ->and($package->generic_inclusions)->toBe(['Roundtrip Airfare', 'Terminal Fee']);
});

it('rejects a room that does not belong to a selected hotel', function () {
    $otherHotel = HotelModel::factory()->create([
        'hotel_name' => 'Far Away Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
    ]);
    $strayRoom = RoomType::factory()->create([
        'hotel_id' => $otherHotel->id,
        'room_name' => 'Stray Room',
        'base_price' => 3000.00,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.packages.store'), validPackagePayload($this, [
            'name' => 'Stray Room Package',
            'room_ids' => [$strayRoom->id],
        ]))
        ->assertSessionHasErrors('room_ids');

    expect(Package::where('name', 'Stray Room Package')->exists())->toBeFalse();
});

it('replaces linked relations on update', function () {
    $package = Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Updatable Package',
        'price' => 5999.00,
    ]);
    $package->hotels()->sync([$this->hotel->id => ['room_type_id' => $this->room->id]]);

    $newHotel = HotelModel::factory()->create([
        'hotel_name' => 'Second Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.packages.update', $package->id), validPackagePayload($this, [
            'name' => 'Updatable Package',
            'hotel_ids' => [$newHotel->id],
            'room_ids' => [],
            'activity_ids' => [],
            'add_on_ids' => [],
        ]))
        ->assertRedirect(route('admin.packages.index'));

    $package->refresh();

    expect($package->hotels->pluck('id')->all())->toBe([$newHotel->id])
        ->and($package->rooms)->toBeEmpty()
        ->and($package->activities)->toBeEmpty()
        ->and($package->addOns)->toBeEmpty();
});

it('includes linked room and add-on names in the package embedding text', function () {
    $package = Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Embedded Package',
        'price' => 6999.00,
    ]);
    $package->hotels()->sync([$this->hotel->id => ['room_type_id' => $this->room->id]]);
    $package->activities()->sync([$this->activity->id]);
    $package->addOns()->sync([$this->addOn->id]);

    $text = app(GeminiService::class)->buildPackageEmbeddingText($package->fresh(['destination', 'hotels', 'rooms', 'activities', 'addOns']));

    expect($text)->toContain('Package Link Resort')
        ->and($text)->toContain('Package Link Suite')
        ->and($text)->toContain('Package Link Cruise')
        ->and($text)->toContain('Package Link Transfer');
});

it('stores inclusions as individual rows', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.packages.store'), validPackagePayload($this, [
            'name' => 'Row Inclusions Package',
            'inclusions' => ['Roundtrip Airfare', 'Terminal Fee', 'Travel Insurance'],
        ]))
        ->assertRedirect(route('admin.packages.index'));

    $package = Package::where('name', 'Row Inclusions Package')->firstOrFail();

    expect($package->generic_inclusions)->toBe(['Roundtrip Airfare', 'Terminal Fee', 'Travel Insurance']);
});

it('updates a package with existing images without demanding a new image', function () {
    $package = Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Image Regression Package',
        'price' => 5999.00,
        'images' => ['packages/existing.png'],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.packages.update', $package->id), validPackagePayload($this, [
            'name' => 'Image Regression Package',
        ]))
        ->assertRedirect(route('admin.packages.index'));

    expect($package->fresh()->images)->toBe(['packages/existing.png']);
});

it('adds a package to the cart at min_pax with price times pax and no room fee', function () {
    $package = Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Min Pax Package',
        'price' => 7999.00,
        'min_pax' => 2,
    ]);
    $user = onboardedUser();

    $this->actingAs($user)->postJson(route('cart.add'), [
        'item_type' => 'package',
        'item_id' => $package->id,
        'quantity' => 2,
        'selected_pax' => 2,
    ])->assertOk()->assertJson(['success' => true]);

    $line = CartItem::where('user_id', $user->id)->where('item_type', 'package')->firstOrFail();

    expect($line->quantity)->toBe(2)
        ->and($line->selected_pax)->toBe(2)
        ->and((float) $line->subtotal)->toBe(15998.00);
});

it('saves the room preference on the package cart line with zero price effect', function () {
    $package = Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Preference Package',
        'price' => 7999.00,
        'min_pax' => 2,
    ]);
    $user = onboardedUser();

    $this->actingAs($user)->postJson(route('cart.add'), [
        'item_type' => 'package',
        'item_id' => $package->id,
        'quantity' => 2,
        'selected_pax' => 2,
    ])->assertOk();

    $line = CartItem::where('user_id', $user->id)->where('item_type', 'package')->firstOrFail();

    $this->actingAs($user)->patchJson(route('cart.update', $line->id), [
        'room_option' => 'separate',
    ])->assertOk();

    expect($line->fresh()->room_option)->toBe('separate')
        ->and($line->fresh()->item_subtitle)->toContain('Separate rooms')
        ->and((float) $line->fresh()->subtotal)->toBe(15998.00);
});

it('carries the room preference into the booking snapshot and shows the SME badge to admins', function () {
    $package = Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Snapshot Preference Package',
        'price' => 7999.00,
        'min_pax' => 2,
    ]);
    $user = onboardedUser();

    CartItem::create([
        'user_id' => $user->id,
        'item_type' => 'package',
        'item_id' => $package->id,
        'quantity' => 2,
        'selected_pax' => 2,
        'room_option' => 'separate',
        'is_selected' => true,
    ]);

    $this->actingAs($user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $user->email,
        'contact_phone' => '09171234567',
        'special_requests' => null,
        'guest_manifest' => null,
    ])->assertOk()->assertJson(['success' => true]);

    $booking = Booking::firstOrFail();
    $item = $booking->items->firstWhere('item_type', 'package');

    expect($item->item_snapshot['room_option'] ?? null)->toBe('separate');

    $this->actingAs($this->admin, 'admin')->get(route('admin.bookings.show', $booking->id))
        ->assertOk()
        ->assertSee('Separate rooms — relay to partner');
});
