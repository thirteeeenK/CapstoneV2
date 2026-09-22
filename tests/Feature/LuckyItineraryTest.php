<?php

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->user = onboardedUser();
});

function createLuckyDestination(string $name, int $activityCount = 2): array
{
    $destination = DestinationModel::factory()->create([
        'name' => $name,
        'region' => 'Test Region',
        'description' => 'A test destination.',
    ]);

    $hotel = HotelModel::factory()->create([
        'hotel_name' => $name.' Beach Resort',
        'destination_id' => $destination->id,
        'specific_address' => 'Station 1',
    ]);

    $room = RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Deluxe Ocean View',
        'base_price' => 1000.00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500.00,
        'total_rooms' => 5,
    ]);

    $activities = collect();
    for ($i = 1; $i <= $activityCount; $i++) {
        $activities->push(ActivityModel::factory()->create([
            'destination_id' => $destination->id,
            'activity_name' => "$name Activity $i",
            'category' => 'Water Activity',
            'activity_level' => 'Adventure',
            'rate' => '₱500/person',
            'duration' => '4 hours',
        ]));
    }

    return compact('destination', 'hotel', 'room', 'activities');
}

it('generates an itinerary where the hotel and every activity are in the same destination', function () {
    createLuckyDestination('El Nido');
    createLuckyDestination('Boracay');
    $dest = DestinationModel::where('name', 'El Nido')->first();

    $response = $this->actingAs($this->user)->postJson(route('lucky.generate'), [
        'destination_id' => $dest->id,
        'max_budget' => 50000,
        'activity_count' => 2,
        'nights' => 2,
        'pax' => 2,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    $itinerary = $response->json('itinerary');

    $room = RoomType::find($itinerary['room']['id']);
    expect((int) $room->hotel->destination_id)->toBe((int) $dest->id);

    foreach ($itinerary['activities'] as $activity) {
        expect((int) ActivityModel::find($activity['id'])->destination_id)->toBe((int) $dest->id);
    }

    foreach ($itinerary['activities'] as $activity) {
        expect($activity)->toHaveKey('is_per_person');
    }
});

it('keeps the total within the budget and flags when nothing fits', function () {
    createLuckyDestination('El Nido');
    $dest = DestinationModel::where('name', 'El Nido')->first();

    // room 1000/night × 2 nights = 2000; 2 activities × (500 × 2 pax) = 2000 → total 4000
    $response = $this->actingAs($this->user)->postJson(route('lucky.generate'), [
        'destination_id' => $dest->id,
        'max_budget' => 4000,
        'activity_count' => 2,
        'nights' => 2,
        'pax' => 2,
    ]);

    $response->assertOk();
    $itinerary = $response->json('itinerary');
    expect($itinerary['total'])->toBeLessThanOrEqual(4000)
        ->and($itinerary['budget_exceeded'])->toBeFalse();

    $tight = $this->actingAs($this->user)->postJson(route('lucky.generate'), [
        'destination_id' => $dest->id,
        'max_budget' => 100,
        'activity_count' => 2,
        'nights' => 2,
        'pax' => 2,
    ]);

    $tight->assertOk();
    $tightItinerary = $tight->json('itinerary');
    expect($tightItinerary['budget_exceeded'])->toBeTrue()
        ->and((float) $tightItinerary['total'])->toBe(4000.0);
});

it('respects activity count, nights, and pax filters', function () {
    createLuckyDestination('El Nido');
    $dest = DestinationModel::where('name', 'El Nido')->first();

    $response = $this->actingAs($this->user)->postJson(route('lucky.generate'), [
        'destination_id' => $dest->id,
        'max_budget' => 50000,
        'activity_count' => 1,
        'nights' => 3,
        'pax' => 3,
    ]);

    $response->assertOk();
    $itinerary = $response->json('itinerary');

    expect($itinerary['activities'])->toHaveCount(1)
        ->and($itinerary['nights'])->toBe(3)
        ->and($itinerary['pax'])->toBe(3)
        ->and((float) $itinerary['room']['nightly_rate'])->toBe(1500.0)
        ->and((int) Carbon::parse($itinerary['check_in_date'])->diffInDays(Carbon::parse($itinerary['check_out_date'])))->toBe(3);
});

it('rejects invalid filter values with a 422', function () {
    createLuckyDestination('El Nido');
    $dest = DestinationModel::where('name', 'El Nido')->first();

    $this->actingAs($this->user)->postJson(route('lucky.generate'), [
        'destination_id' => $dest->id,
        'activity_count' => 99,
        'nights' => 0,
        'max_budget' => 0,
    ])->assertStatus(422);

    $this->actingAs($this->user)->postJson(route('lucky.generate'), [
        'destination_id' => 999999,
    ])->assertStatus(422);
});

it('adds the accepted itinerary to the cart as a single lucky group', function () {
    $data = createLuckyDestination('El Nido');
    $dest = $data['destination'];
    $activityIds = $data['activities']->pluck('id')->all();

    $response = $this->actingAs($this->user)->postJson(route('lucky.accept'), [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => $activityIds,
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 4000,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure(['group_id', 'cart_count', 'cart_total'])
        ->assertJsonMissingPath('redirect_url');

    $items = CartItem::where('user_id', $this->user->id)->orderBy('item_type')->get();
    expect($items)->toHaveCount(3);

    $roomItem = $items->firstWhere('item_type', 'room');
    expect($roomItem->lucky_group_id)->not->toBeNull()
        ->and($roomItem->check_in_date->format('Y-m-d'))->toBe('2026-12-01')
        ->and($roomItem->check_out_date->format('Y-m-d'))->toBe('2026-12-03')
        ->and($roomItem->selected_pax)->toBe(2)
        ->and($roomItem->is_selected)->toBeTrue();

    $activityItems = $items->where('item_type', 'activity');
    expect($activityItems)->toHaveCount(2)
        ->and($activityItems->pluck('lucky_group_id')->unique())->toHaveCount(1)
        ->and($activityItems->first()->lucky_group_id)->toBe($roomItem->lucky_group_id);
});

it('creates separate cart lines when the same lucky itinerary is accepted twice', function () {
    $data = createLuckyDestination('El Nido');
    $dest = $data['destination'];
    $activityIds = $data['activities']->pluck('id')->all();

    $payload = [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => $activityIds,
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 4000,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ];

    $first = $this->actingAs($this->user)->postJson(route('lucky.accept'), $payload)->assertOk();
    $second = $this->actingAs($this->user)->postJson(route('lucky.accept'), $payload)->assertOk();

    expect($second->json('group_id'))->not->toBe($first->json('group_id'));

    $rooms = CartItem::where('user_id', $this->user->id)->where('item_type', 'room')->get();
    expect($rooms)->toHaveCount(2)
        ->and($rooms->pluck('quantity')->all())->each->toBe(1)
        ->and($rooms->pluck('lucky_group_id')->unique())->toHaveCount(2);

    $activities = CartItem::where('user_id', $this->user->id)->where('item_type', 'activity')->get();
    expect($activities)->toHaveCount(count($activityIds) * 2);
});

it('rejects tampered or over-budget accepts and requires authentication', function () {
    $data = createLuckyDestination('El Nido');
    createLuckyDestination('Boracay');
    $dest = $data['destination'];
    $otherDest = DestinationModel::where('name', 'Boracay')->first();
    $foreignActivity = ActivityModel::where('destination_id', $otherDest->id)->first();
    $activityIds = $data['activities']->pluck('id')->all();

    // Over budget without acknowledging the budget_exceeded flag
    $this->actingAs($this->user)->postJson(route('lucky.accept'), [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => $activityIds,
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 100,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ])->assertStatus(422);

    // Activity from a different destination
    $this->actingAs($this->user)->postJson(route('lucky.accept'), [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => array_merge($activityIds, [$foreignActivity->id]),
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 4000,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ])->assertStatus(422);
});

it('requires authentication for the lucky pages', function () {
    $this->get(route('lucky.index'))->assertRedirect(route('login'));
    $this->post(route('lucky.accept'), [])->assertRedirect(route('login'));
    $this->post(route('lucky.generate'), [])->assertRedirect(route('login'));
});

it('exposes lucky groups in cart data and toggles the whole group at once', function () {
    $data = createLuckyDestination('El Nido');
    $dest = $data['destination'];
    $activityIds = $data['activities']->pluck('id')->all();

    $this->actingAs($this->user)->postJson(route('lucky.accept'), [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => $activityIds,
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 4000,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ])->assertOk();

    $dataRes = $this->actingAs($this->user)->getJson(route('cart.data'));
    $dataRes->assertOk()->assertJson(['success' => true]);

    $groups = $dataRes->json('groups');
    expect($groups)->toHaveCount(1)
        ->and($groups[0]['item_count'])->toBe(3)
        ->and($groups[0]['is_selected'])->toBeTrue()
        ->and($groups[0]['destination_name'])->toBe('El Nido')
        ->and(str_contains($groups[0]['title'], 'Surprise Itinerary'))->toBeTrue();

    $groupId = $groups[0]['id'];

    $toggleRes = $this->actingAs($this->user)->postJson(route('cart.toggle-group', $groupId));
    $toggleRes->assertOk();
    expect(CartItem::where('user_id', $this->user->id)->where('is_selected', true)->count())->toBe(0);
    $toggledGroup = collect($toggleRes->json('groups'))->firstWhere('id', $groupId);
    expect($toggledGroup['is_selected'])->toBeFalse();

    $this->actingAs($this->user)->postJson(route('cart.toggle-group', $groupId))->assertOk();
    expect(CartItem::where('user_id', $this->user->id)->where('is_selected', true)->count())->toBe(3);
});

it('books a lucky group through the existing checkout flow unchanged', function () {
    $data = createLuckyDestination('El Nido');
    $dest = $data['destination'];
    $activityIds = $data['activities']->pluck('id')->all();

    $this->actingAs($this->user)->postJson(route('lucky.accept'), [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => $activityIds,
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 4000,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ])->assertOk();

    $processRes = $this->actingAs($this->user)->postJson(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
    ]);

    $processRes->assertOk()->assertJson(['success' => true]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(Booking::STATUS_PENDING)
        ->and($booking->total_amount)->toBe('4000.00')
        ->and($booking->items)->toHaveCount(3);

    expect($booking->items->where('item_type', 'room')->count())->toBe(1)
        ->and($booking->items->where('item_type', 'activity')->count())->toBe(2);

    expect(CartItem::where('user_id', $this->user->id)->count())->toBe(0);
});

it('scales a bare unit activity rate per pax so preview and basket totals match', function () {
    $data = createLuckyDestination('El Nido');
    $dest = $data['destination'];
    $van = ActivityModel::factory()->create([
        'destination_id' => $dest->id,
        'activity_name' => '6-7 Destinations Aircon Van',
        'category' => 'Land Tour',
        'activity_level' => 'Sightseeing',
        'rate' => '₱3,700/person',
        'duration' => '8 hours',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('lucky.accept'), [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => [$van->id],
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 10000,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ])->assertOk();

    $activityItem = CartItem::where('user_id', $this->user->id)->where('item_type', 'activity')->first();
    // room 1000/night × 2 nights = 2000 + van 3700 × 2 pax = 7400 → 9400 on both sides
    expect($activityItem->subtotal)->toBe(7400.0)
        ->and((float) $response->json('cart_total'))->toBe(9400.0);

    // Per-person rates still scale with pax.
    $perPerson = $data['activities']->first();
    $this->actingAs($this->user)->postJson(route('cart.add'), [
        'item_type' => 'activity',
        'item_id' => $perPerson->id,
        'quantity' => 1,
        'selected_pax' => 2,
    ])->assertOk()->assertJson(['success' => true]);

    $scaled = CartItem::where('user_id', $this->user->id)
        ->where('item_type', 'activity')->where('item_id', $perPerson->id)->first();
    expect($scaled->subtotal)->toBe(1000.0);
});

it('charges a genuine flat hourly activity rate once for 2 pax on both sides', function () {
    $data = createLuckyDestination('El Nido');
    $dest = $data['destination'];
    $kayak = ActivityModel::factory()->create([
        'destination_id' => $dest->id,
        'activity_name' => 'Kayak Rental',
        'category' => 'Water Activity',
        'activity_level' => 'Relaxing',
        'rate' => '₱400/hour',
        'duration' => '1 hour',
    ]);

    expect($kayak->isPerPersonRate())->toBeFalse();

    $preview = $this->actingAs($this->user)->postJson(route('lucky.generate'), [
        'destination_id' => $dest->id,
        'max_budget' => 50000,
        'activity_count' => 3,
        'nights' => 2,
        'pax' => 2,
    ])->assertOk()->json('itinerary');

    $previewActivity = collect($preview['activities'])->firstWhere('id', $kayak->id);
    // Flat hourly rate: one charge regardless of pax.
    expect($previewActivity['is_per_person'])->toBeFalse()
        ->and((float) $previewActivity['rate_for_pax'])->toBe(400.0);

    $response = $this->actingAs($this->user)->postJson(route('lucky.accept'), [
        'destination_id' => $dest->id,
        'room_id' => $data['room']->id,
        'activity_ids' => [$kayak->id],
        'nights' => 2,
        'pax' => 2,
        'max_budget' => 50000,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-03',
    ])->assertOk();

    $activityItem = CartItem::where('user_id', $this->user->id)->where('item_type', 'activity')->first();
    // room 2000 + flat 400 = 2400 on both sides
    expect($activityItem->subtotal)->toBe(400.0)
        ->and((float) $response->json('cart_total'))->toBe(2400.0);
});
