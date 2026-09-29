<?php

use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\Chat\ChatJudgeEvidenceContext;

test('it rebuilds the full room evidence from a returned room card', function () {
    $destination = DestinationModel::factory()->create(['name' => 'Evidence Coast']);
    $hotel = HotelModel::factory()->create(['destination_id' => $destination->id]);
    $room = RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Evidence Family Suite',
        'base_price' => 4500,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500,
        'bed_configuration' => '1 King Bed and 1 Sofa Bed',
        'room_amenities' => ['WiFi', 'Air Conditioning'],
    ]);

    $context = app(ChatJudgeEvidenceContext::class)->build([
        'retrieved_rooms' => [[
            'id' => $room->id,
            'similarity_score' => 1.0,
            'pax' => 4,
        ]],
    ]);

    expect($context)
        ->toContain("ROOM[id={$room->id}].name = Evidence Family Suite")
        ->toContain('Maximum Occupancy: 4 pax')
        ->toContain('Bed Configuration: 1 King Bed and 1 Sofa Bed')
        ->toContain('Amenities: WiFi, Air Conditioning')
        ->toContain('Verified Quote:');
});

test('it returns an explicit empty-evidence marker when no cards were returned', function () {
    expect(app(ChatJudgeEvidenceContext::class)->build([]))
        ->toBe('(no records were retrieved for this query)');
});
