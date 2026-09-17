<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;

it('renders second-destination activities on the landing page despite the six-card cap', function () {
    $first = DestinationModel::create([
        'name' => 'First Island (test)',
        'description' => 'First.',
        'latitude' => 11.0,
        'longitude' => 121.0,
    ]);

    foreach (range(1, 7) as $i) {
        ActivityModel::create([
            'destination_id' => $first->id,
            'activity_name' => "First Island Activity {$i} (test)",
            'category' => 'Land Tour',
            'activity_level' => 'Sightseeing',
            'rate' => '₱1,000',
            'is_shown' => true,
        ]);
    }

    $second = DestinationModel::create([
        'name' => 'Second Island (test)',
        'description' => 'Second.',
        'latitude' => 12.0,
        'longitude' => 122.0,
    ]);

    ActivityModel::create([
        'destination_id' => $second->id,
        'activity_name' => 'Second Island Lagoon Tour (test)',
        'category' => 'Island Hopping',
        'activity_level' => 'Adventure',
        'rate' => '₱1,200',
        'is_shown' => true,
    ]);

    $content = $this->get(route('landing'))->assertOk()->getContent();

    // Card is server-rendered regardless of global loop position.
    expect($content)->toContain('Second Island Lagoon Tour (test)')
        // Per-destination cap clause lets the active pill show its own first six.
        ->toContain("activeTab === '{$second->id}'", false)
        // Toggle counts carry the per-destination total.
        ->toContain("'{$second->id}': 1", false);
});
