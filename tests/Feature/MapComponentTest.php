<?php

test('map component emits select event instead of bindPopup View details', function () {
    $markers = [['type' => 'hotel', 'id' => 1, 'name' => 'A', 'lat' => 12, 'lng' => 122, 'url' => '/hotels/1']];
    $html = view('components.frontend.map', ['markers' => $markers, 'center' => ['lat' => 12, 'lng' => 122], 'zoom' => 7])->render();
    expect($html)->toContain('sunnytrip:map-select')
        ->toContain('highlight')
        ->toContain('setFilter');
    expect($html)->not->toContain('View details →</a>');
});

test('map component strips blade comments so inline script parses', function () {
    $markers = [['type' => 'hotel', 'id' => 1, 'name' => 'A', 'lat' => 12, 'lng' => 122, 'url' => '/hotels/1']];
    $html = view('components.frontend.map', ['markers' => $markers, 'center' => ['lat' => 12, 'lng' => 122], 'zoom' => 7])->render();
    // A malformed `{ { -- ... -- } }` comment leaks into the <script> and kills
    // the whole map with `Unexpected identifier` (white canvas, no fallback).
    expect($html)->not->toContain('{ { --')
        ->not->toContain('Geolocation core');
});

test('map component renders a retry fallback for cdn failure and empty markers', function () {
    $markers = [['type' => 'hotel', 'id' => 1, 'name' => 'A', 'lat' => 12, 'lng' => 122, 'url' => '/hotels/1']];
    $html = view('components.frontend.map', ['markers' => $markers, 'center' => ['lat' => 12, 'lng' => 122], 'zoom' => 7])->render();
    expect($html)->toContain('Interactive map is unavailable right now.')
        ->toContain('Retry')
        ->toContain('-fallback')
        ->toContain('tiles-banner')
        ->toContain('tileerror')
        ->toContain('markers.length === 0');
});
