<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->destination = DestinationModel::factory()->create([
        'name' => 'Test Island',
        'region' => 'Test Region',
        'description' => 'A test destination.',
    ]);

    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Test Beach Resort',
        'destination_id' => $this->destination->id,
        'specific_address' => 'Station 1',
    ]);

    $this->room = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_name' => 'Deluxe Ocean View',
        'base_price' => 2500.00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500.00,
        'total_rooms' => 5,
        'bed_configuration' => '1 King Bed',
        'room_size' => '35 sqm',
        'room_amenities' => ['WiFi', 'Pool'],
    ]);

    $this->activity = ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Island Hopping',
        'category' => 'Water Activity',
        'activity_level' => 'Adventure',
        'rate' => '₱500/person',
        'duration' => '4 hours',
        'capacity' => 'Up to 12 guests',
        'inclusions' => ['Lunch', 'Life vest'],
    ]);
});

test('room preview endpoint returns the shared payload shape', function () {
    $response = $this->getJson("/rooms/{$this->room->id}/preview");

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'room_name',
            'base_price',
            'base_occupancy',
            'max_occupancy',
            'extra_person_fee',
            'occupancy',
            'bed_configuration',
            'images',
            'amenities',
            'is_shown',
        ])
        ->assertJson([
            'id' => $this->room->id,
            'room_name' => 'Deluxe Ocean View',
            'base_occupancy' => 2,
            'max_occupancy' => 4,
            'extra_person_fee' => 500.0,
        ]);

    expect($response->json('images'))->toBeArray()
        ->and($response->json('amenities'))->toEqual(['WiFi', 'Pool']);
});

test('room preview returns 404 for hidden rooms when not an admin', function () {
    $this->room->update(['is_shown' => false]);

    $this->getJson("/rooms/{$this->room->id}/preview")
        ->assertNotFound();
});

test('room preview returns 404 for a nonexistent room', function () {
    $this->getJson('/rooms/999999/preview')
        ->assertNotFound();
});

test('activity preview endpoint returns the shared payload shape', function () {
    $response = $this->getJson("/activities/{$this->activity->id}/preview");

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'activity_name',
            'category',
            'category_icon',
            'rate',
            'duration',
            'activity_level',
            'capacity',
            'destination_name',
            'images',
            'inclusions',
        ])
        ->assertJson([
            'id' => $this->activity->id,
            'activity_name' => 'Island Hopping',
            'rate' => '₱500/person',
            'destination_name' => 'Test Island',
        ]);

    expect($response->json('inclusions'))->toEqual(['Lunch', 'Life vest'])
        ->and($response->json('images'))->toBeArray();
});

test('activity preview returns 404 for hidden activities when not an admin', function () {
    $this->activity->update(['is_shown' => false]);

    $this->getJson("/activities/{$this->activity->id}/preview")
        ->assertNotFound();
});

test('activity preview returns 404 for a nonexistent activity', function () {
    $this->getJson('/activities/999999/preview')
        ->assertNotFound();
});
