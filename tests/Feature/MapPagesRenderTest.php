<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;

test('explore page renders with focus param and map apis', function () {
    $user = onboardedUser();
    $dest = DestinationModel::factory()->create(['latitude' => '12', 'longitude' => '122']);
    HotelModel::factory()->create(['destination_id' => $dest->id, 'is_shown' => true, 'latitude' => '12.1', 'longitude' => '122.1']);
    ActivityModel::factory()->create(['destination_id' => $dest->id, 'is_shown' => true, 'latitude' => '12.2', 'longitude' => '122.2']);

    $res = $this->actingAs($user)->get(route('explore', ['focus' => "destination:{$dest->id}"]));
    $res->assertOk();
    $content = $res->getContent();
    expect($content)->toContain('exploreMap');
    expect($content)->toContain('setFilter');
    expect($content)->toContain('selectWithCard');
    expect($content)->toContain('cheapest_room_id');
    expect($content)->toContain('openActivityById');
});

test('explore hotel marker exposes cheapest room id', function () {
    $user = onboardedUser();
    $dest = DestinationModel::factory()->create(['latitude' => '12', 'longitude' => '122']);
    $hotel = HotelModel::factory()->create(['destination_id' => $dest->id, 'is_shown' => true, 'latitude' => '12.1', 'longitude' => '122.1']);
    $cheap = RoomType::factory()->create(['hotel_id' => $hotel->id, 'is_shown' => true, 'base_price' => 2000]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'is_shown' => true, 'base_price' => 4500]);

    $res = $this->actingAs($user)->get(route('explore', ['focus' => "hotel:{$hotel->id}"]));
    $res->assertOk();
    expect($res->getContent())->toContain('"cheapest_room_id":'.$cheap->id);
});

test('dashboard renders map preview wrapper', function () {
    $user = onboardedUser();
    $dest = DestinationModel::factory()->create([
        'latitude' => '12',
        'longitude' => '122',
        'image' => 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&w=1000&q=80',
    ]);

    $res = $this->actingAs($user)->get(route('dashboard'));
    $res->assertOk();
    $res->assertSee('dashboardMap');
    $res->assertSee('sunnytrip:map-select');
    $res->assertSee('resolveImage');
    expect($res->getContent())->toContain('"cover_image"');
    expect($res->getContent())->toContain($dest->name);
});

test('dashboard activity cards open per-activity preview modal', function () {
    $user = onboardedUser();
    $dest = DestinationModel::factory()->create(['latitude' => '12', 'longitude' => '122']);
    $act = ActivityModel::factory()->create(['destination_id' => $dest->id, 'is_shown' => true]);

    $res = $this->actingAs($user)->get(route('dashboard'));
    $res->assertOk();
    $content = $res->getContent();
    expect($content)->toContain('View Experience');
    expect($content)->toContain('openActivityById('.$act->id.')');
});
