<?php

namespace Database\Seeders;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use Illuminate\Database\Seeder;

class ActivityCoordinatesSeeder extends Seeder
{
    /**
     * Real-world approximate coordinates for every seeded activity, keyed by
     * destination name then activity name. Uses a plain update so other
     * columns (descriptions, embeddings, etc.) are never touched.
     */
    public function run(): void
    {
        $coords = [
            'Boracay' => [
                // Island hopping meet-up at White Beach Station 2
                'Joiners Island Hopping' => [11.9658, 121.9260],

                // Land tour variants depart from different White Beach stations
                '6–7 Destinations (E-Trike)' => [11.9658, 121.9260],
                '6–7 Destinations (FB Van/Multicab)' => [11.9715, 121.9243],
                '6–7 Destinations (Aircon Van)' => [11.9595, 121.9290],

                // Water sports run off Bulabog Beach / White Beach
                'Parasailing' => [11.9702, 121.9293],
                'Parasailing (Solo)' => [11.9725, 121.9290],
                'Banana Boat' => [11.9595, 121.9290],
                'UFO Ride' => [11.9680, 121.9248],
                'Jet Ski (15 mins)' => [11.9700, 121.9295],
                'Jet Ski (30 mins)' => [11.9685, 121.9298],

                // Boat parties dock near Station 3
                'Party Yacht' => [11.9590, 121.9285],
                'Party Boat' => [11.9585, 121.9288],

                // Diving in the LGU area off Station 1
                'Helmet Diving' => [11.9720, 121.9245],
                'Scuba Diving' => [11.9700, 121.9260],

                // ATV / zipline combos on mainland Malay (Aklan)
                'ATV + Zipline Package (Shingley Company)' => [11.9270, 121.9450],
                'ATV + Zipline Package (Mega Paraw Company)' => [11.9260, 121.9440],
                'ATV Only (Mainland)' => [11.9280, 121.9460],
                'Zipline Only' => [11.9275, 121.9455],

                // Newcoast premium trail
                'ATV (New Coast)' => [11.9560, 121.9700],

                // Traditional paraw sailing along White Beach
                'Paraw Sailing (Private)' => [11.9658, 121.9255],
                'Paraw Sailing (Joiners)' => [11.9720, 121.9238],

                // Clear kayak photo sessions off White Beach
                'Crystal Kayak' => [11.9600, 121.9286],
            ],

            'El Nido' => [
                // Signature stop per island-hopping tour (boats depart El Nido port)
                'El Nido Tour A (Lagoons & Beaches)' => [11.1870, 119.4170],
                'El Nido Tour B (Caves & Coves)' => [11.2200, 119.4150],
                'El Nido Tour C (Hidden Beaches & Shrines)' => [11.2450, 119.4120],
                'El Nido Tour D (Island Beaches)' => [11.1860, 119.4145],

                // In-town canopy walk over the Taraw cliff
                'Taraw Cliff Canopy Walk' => [11.1796, 119.3928],

                // Northern golden-sand beach
                'Nacpan Beach Inland Tour' => [11.3330, 119.4000],

                // Dive shops in El Nido town
                'Discover Scuba Diving (DSD)' => [11.1805, 119.3950],

                // Calm-water rentals & cruises along Corong-Corong and Lio
                'Clear Kayak Rental' => [11.1520, 119.3990],
                'Sunset Cruise / Party Boat' => [11.1515, 119.3985],
                'Stand-Up Paddleboarding (SUP)' => [11.1290, 119.3990],
            ],
        ];

        $updated = 0;
        $missing = [];

        foreach ($coords as $destinationName => $activities) {
            $destination = DestinationModel::where('name', $destinationName)->first();
            if (! $destination) {
                $missing[] = "[Destination] {$destinationName}";

                continue;
            }

            foreach ($activities as $name => [$lat, $lng]) {
                $count = ActivityModel::where('destination_id', $destination->id)
                    ->where('activity_name', $name)
                    ->update([
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ]);

                if ($count) {
                    $updated += $count;
                } else {
                    $missing[] = "[Activity] {$name} ({$destinationName})";
                }
            }
        }

        $this->command->info("ActivityCoordinatesSeeder: set coordinates for {$updated} activity record(s).");

        if (! empty($missing)) {
            $this->command->warn('Not found (skipped): '.implode(', ', $missing));
        }
    }
}
