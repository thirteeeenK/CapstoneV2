<?php

namespace Database\Seeders;

use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Services\GeminiService;
use Illuminate\Database\Seeder;

class AddOnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GeminiService $geminiService): void
    {
        $boracay = DestinationModel::where('name', 'Boracay')->first();

        if (! $boracay) {
            $this->command->error('Boracay destination not found. Please run DestinationSeeder first.');

            return;
        }

        $addons = [
            [
                'destination_id' => $boracay->id,
                'name' => 'Airport to Hotel Roundtrip Transfer',
                'type' => 'Transfer',
                'description' => 'Complete hassle-free airport to hotel roundtrip transfer service in Boracay including van, port fees, environmental fees, boat tickets, e-trike/multicab hotel drop-off, and tour guide assistance.',
                'inclusions' => [
                    'All-in service',
                    'All fees included',
                    'Van from Airport to Port',
                    'Terminal fee',
                    'Environmental Fee',
                    'Boat Ticket',
                    'E-trike or Multicab from Port to Hotel',
                    'Vice Versa / Roundtrip',
                    'With Tourguide Assistance',
                ],
                'pricing_tiers' => [
                    ['min_pax' => 1, 'max_pax' => 1, 'rate' => 1850],
                    ['min_pax' => 2, 'max_pax' => 2, 'rate' => 1450],
                    ['min_pax' => 3, 'max_pax' => 3, 'rate' => 1250],
                    ['min_pax' => 4, 'max_pax' => 4, 'rate' => 1150],
                    ['min_pax' => 5, 'max_pax' => 5, 'rate' => 1100],
                    ['min_pax' => 6, 'max_pax' => 6, 'rate' => 1050],
                    ['min_pax' => 7, 'max_pax' => 15, 'rate' => 1000],
                    ['min_pax' => 16, 'max_pax' => 999, 'rate' => 950],
                ],
                'surcharges' => [
                    ['name' => 'Station 1 or Station 0 Hotel Drop-off Charge', 'amount' => 200, 'type' => 'per_pax'],
                ],
                'is_shown' => true,
            ],
        ];

        foreach ($addons as $addonData) {
            $addon = AddOnModel::updateOrCreate(
                [
                    'destination_id' => $addonData['destination_id'],
                    'name' => $addonData['name'],
                ],
                $addonData
            );

            // Generate AI Vector Embedding
            $text = $geminiService->buildAddOnEmbeddingText($addon, $boracay->name);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $addon->name);
            if ($vector) {
                $addon->embedding = $geminiService->formatVectorForDb($vector);
                $addon->save();
            }
        }
    }
}
