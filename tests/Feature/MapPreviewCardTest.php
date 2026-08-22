<?php

test('map-preview-card view renders for destination marker', function () {
    $html = view('components.frontend.map-preview-card', [
        'marker' => [
            'type' => 'destination', 'name' => 'Palawan', 'subtitle' => null,
            'hotel_count' => 3, 'activity_count' => 5, 'cover_image' => 'https://example.com/a.jpg',
            'weather' => ['icon' => '01d', 'description' => 'clear sky', 'temp' => 30],
            'rating' => 4.7, 'review_count' => 12, 'url' => '/hotels?destination=1',
        ],
    ])->render();

    expect($html)->toContain('Palawan')
        ->toContain('3 stays')->toContain('5 experiences')
        ->toContain('Explore Hotels');
});

test('map-preview-card renders hotel cheapest price', function () {
    $html = view('components.frontend.map-preview-card', [
        'marker' => [
            'type' => 'hotel', 'name' => 'Sunny Resort', 'subtitle' => 'Boracay', 'lat' => 12, 'lng' => 122,
            'address' => 'Beach Rd', 'rating' => 4.8, 'review_count' => 44,
            'cover_image' => 'https://example.com/h.jpg', 'cheapest_price' => 2999,
            'vibe_tags' => ['chill', 'luxury'], 'distance_label' => '2.1 km',
            'url' => '/hotels/1',
        ],
    ])->render();

    expect($html)->toContain('Sunny Resort')->toContain('2999');
});
