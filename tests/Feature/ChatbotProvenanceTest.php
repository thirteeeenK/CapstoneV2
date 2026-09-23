<?php

use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => [
                'values' => unitVector(0),
            ],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Here is a great hotel!'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Provenance Palm Resort',
        'destination_id' => $destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
});

test('hotel search grounded prompt carries provenance keys', function () {
    $response = $this->postJson('/chat', ['message' => 'find hotels in Boracay']);
    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('retrieved_hotels') ?? [])->toHaveCount(1);

    $bodies = collect(Http::recorded())
        ->filter(fn ($record) => str_contains($record[0]->url(), 'generateContent'))
        ->map(fn ($record) => $record[0]->body())
        ->values();
    expect($bodies)->not->toBeEmpty();

    $last = $bodies->last();
    expect($last)->toContain('HOTEL[id=')
        ->and($last)->toContain("HOTEL[id={$this->hotel->id}].name");
});
