<?php

namespace Tests\Unit;

use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbedStaleTest extends TestCase
{
    public function test_stale_option_re_embeds_only_changed_rows(): void
    {
        // Seed a destination
        $destination = DestinationModel::factory()->create(['name' => 'Test Stale Dest']);

        // Create a hotel
        $hotel = HotelModel::factory()->create([
            'destination_id' => $destination->id,
            'hotel_name' => 'Stale Test Hotel',
            'hotel_description' => 'Original description.',
        ]);

        // Fake Gemini embedding response (unit vector to avoid zero-vector cosine issues)
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => array_fill(0, 3072, 0.01)],
            ], 200),
        ]);

        // First embed
        Artisan::call('embed:all', ['--force' => true]);
        $hotel->refresh();

        $this->assertNotNull($hotel->embedding);
        $this->assertEquals('text-embedding-001', $hotel->embedding_model);
        $this->assertNotNull($hotel->embedding_source_hash);
        $this->assertNotNull($hotel->embedded_at);

        $firstEmbeddedAt = $hotel->embedded_at;
        $firstHash = $hotel->embedding_source_hash;

        // Change description
        $hotel->hotel_description = 'Updated description with new content.';
        $hotel->save();

        // Ensure timestamp advances (test runs fast, force 1s gap)
        sleep(1);

        // Run stale - should re-embed this hotel
        Artisan::call('embed:all', ['--stale' => true]);
        $hotel->refresh();

        $this->assertNotEquals($firstEmbeddedAt, $hotel->embedded_at, 'embedded_at should update when content changes');
        $this->assertNotEquals($firstHash, $hotel->embedding_source_hash, 'source hash should update when content changes');

        // Run stale again - should skip (fresh)
        $secondEmbeddedAt = $hotel->embedded_at;
        Artisan::call('embed:all', ['--stale' => true]);
        $hotel->refresh();

        $this->assertEquals($secondEmbeddedAt, $hotel->embedded_at, 'embedded_at should NOT update when content unchanged');
    }

    public function test_stale_option_with_model_change(): void
    {
        $destination = DestinationModel::factory()->create(['name' => 'Test Model Change Dest']);
        $hotel = HotelModel::factory()->create([
            'destination_id' => $destination->id,
            'hotel_name' => 'Model Change Hotel',
            'hotel_description' => 'Description for model change test.',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => array_fill(0, 3072, 0.01)],
            ], 200),
        ]);

        // Embed with current model
        Artisan::call('embed:all', ['--force' => true]);
        $hotel->refresh();

        // Manually change model to simulate model upgrade
        $hotel->embedding_model = 'old-model-v1';
        $hotel->save();

        // Run stale - should re-embed because model differs
        Artisan::call('embed:all', ['--stale' => true]);
        $hotel->refresh();

        $this->assertEquals('text-embedding-001', $hotel->embedding_model, 'model should update to current');
    }
}
