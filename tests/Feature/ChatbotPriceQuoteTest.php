<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\Chat\CatalogPriceQuote;
use App\Services\GeminiService;

test('catalog quotes apply only the pricing basis stored on each record', function () {
    $quotes = app(CatalogPriceQuote::class);

    $room = RoomType::factory()->make(['base_price' => 1000, 'base_occupancy' => 2, 'max_occupancy' => 4, 'extra_person_fee' => 250]);
    $activity = ActivityModel::factory()->make(['rate' => '₱500/person']);
    $hourly = ActivityModel::factory()->make(['rate' => '₱300–₱500/hour']);
    $package = Package::factory()->make(['price' => 5000]);
    $addon = AddOnModel::factory()->make(['pricing_tiers' => [
        ['min_pax' => 1, 'max_pax' => 4, 'total_rate' => 1200],
    ]]);

    expect($quotes->for($room, 3, 2))->toMatchArray(['basis' => 'per_night', 'total' => 2500.0])
        ->and($quotes->for($activity, 3))->toMatchArray(['basis' => 'per_person', 'total' => 1500.0])
        ->and($quotes->for($hourly, 3))->toMatchArray(['basis' => 'per_hour', 'total' => 300.0, 'total_max' => 500.0])
        ->and($quotes->for($package, 3))->toMatchArray(['basis' => 'package_per_pax', 'total' => 15000.0])
        ->and($quotes->for($addon, 3))->toMatchArray(['basis' => 'tiered_total', 'total' => 1200.0]);
});

test('pricing augmentation does not multiply unrelated numbers from prompt context', function () {
    $service = app(GeminiService::class);
    $context = 'Capacity: 12 guests. Duration: 3 hours. Rate: ₱300–₱500/hour.';

    expect($service->extractPricingContext($context, 'magkano for 4 pax for 2 days'))
        ->toBe($context)
        ->not->toContain('Precomputed');
});
