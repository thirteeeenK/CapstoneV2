<?php

test('map component emits select event instead of bindPopup View details', function () {
    $markers = [['type' => 'hotel', 'id' => 1, 'name' => 'A', 'lat' => 12, 'lng' => 122, 'url' => '/hotels/1']];
    $html = view('components.frontend.map', ['markers' => $markers, 'center' => ['lat' => 12, 'lng' => 122], 'zoom' => 7])->render();
    expect($html)->toContain('sunnytrip:map-select')
        ->toContain('highlight')
        ->toContain('setFilter');
    expect($html)->not->toContain('View details →</a>');
});
