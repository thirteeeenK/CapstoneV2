<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\MapService;

test('destinationMarkers includes enriched optional keys without breaking existing keys', function () {
    $dest = DestinationModel::factory()->create([
        'latitude' => '12.0', 'longitude' => '122.0',
    ]);
    HotelModel::factory()->create([
        'destination_id' => $dest->id, 'latitude' => '12.1', 'longitude' => '122.1',
        'is_shown' => true, 'images' => ['hotels/a.jpg'],
    ]);

    $svc = app(MapService::class);
    $markers = $svc->destinationMarkers();

    expect($markers)->not->toBeEmpty();
    $m = collect($markers)->firstWhere('id', $dest->id);
    expect($m)->toHaveKeys(['type', 'id', 'name', 'lat', 'lng', 'hotel_count', 'activity_count', 'weather', 'url']);
    // new additive keys must exist (nullable) — cover_image is always a string (Unsplash fallback)
    expect($m)->toHaveKeys(['cover_image', 'destination_slug']);
    expect($m['type'])->toBe('destination');
    expect($m['cover_image'])->toBeString();
    expect($m['destination_slug'])->toBeString();
});

test('allMarkers hotel and activity carry enriched fields', function () {
    $dest = DestinationModel::factory()->create(['latitude' => '12', 'longitude' => '122']);
    $hotel = HotelModel::factory()->create([
        'destination_id' => $dest->id, 'is_shown' => true,
        'latitude' => '12.1', 'longitude' => '122.1', 'vibe_tags' => ['chill', 'luxury'],
        'featured_amenities' => ['Pool', 'Spa'], 'images' => ['hotels/a.jpg'],
    ]);
    // create a room to test cheapest_price (adjust factory fields to match RoomType)
    RoomType::factory()->create([
        'hotel_id' => $hotel->id, 'is_shown' => true, 'base_price' => 2500,
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id, 'is_shown' => true, 'base_price' => 4000,
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $dest->id, 'is_shown' => true,
        'latitude' => '12.2', 'longitude' => '122.2', 'category' => 'island-hopping',
        'rate' => 1200, 'vibe_tags' => ['adventure'],
    ]);

    $markers = app(MapService::class)->allMarkers();
    $h = collect($markers)->firstWhere(fn ($x) => $x['type'] === 'hotel');
    $a = collect($markers)->firstWhere(fn ($x) => $x['type'] === 'activity');

    expect($h)->toHaveKeys(['cheapest_price', 'images', 'vibe_tags', 'featured_amenities']);
    // Pest `or` chaining is broken in this version; use loose float check instead of `->toBe(2500.0)->or->toBe(2500)`
    expect((float) $h['cheapest_price'])->toBe(2500.0);
    expect($h['images'])->toBeArray();
    expect($h['vibe_tags'])->toBe(['chill', 'luxury']);
    expect($h['featured_amenities'])->toBe(['Pool', 'Spa']);
    expect($a)->toHaveKeys(['images', 'vibe_tags', 'category', 'rate']);
    expect($a['images'])->toBeArray();
    expect($a['vibe_tags'])->toBe(['adventure']);
});
