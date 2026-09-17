<?php

namespace Database\Seeders;

use App\Models\DestinationModel;
use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boracay = DestinationModel::where('name', 'Boracay')->first();

        if (! $boracay) {
            return;
        }

        $packages = [
            [
                'name' => 'Boracay Tipid Deal',
                'type' => 'Flight + Hotel + Transfer',
                'price' => 6999.00000000,
                'days' => 3,
                'nights' => 2,
                'min_pax' => 2,
                'valid_from' => '2026-08-01',
                'valid_to' => '2026-12-31',
                'generic_inclusions' => [
                    'Roundtrip Airfare',
                    'Roundtrip Transfer',
                    '2 Nights Hotel Accommodation',
                    'Van and Boat Transfer',
                    'Terminal Fee',
                    'Environmental Fee',
                    'Taxes and Permits',
                    'Travel Coordinator',
                    'Travel Requirements Assistance',
                ],
                'images' => [],
                'is_active' => true,
            ],

            [
                'name' => 'Boracay Sulit Deal',
                'type' => 'Flight + Hotel + Transfer + Land Tour',
                'price' => 8699.00000000,
                'days' => 3,
                'nights' => 2,
                'min_pax' => 2,
                'valid_from' => '2026-08-01',
                'valid_to' => '2026-12-31',
                'generic_inclusions' => [
                    'Roundtrip Airfare',
                    'Roundtrip Transfer',
                    '2 Nights Hotel Accommodation',
                    'Van and Boat Transfer',
                    'Land Tour',
                    'Terminal Fee',
                    'Environmental Fee',
                    'Taxes and Permits',
                    'Travel Coordinator',
                    'Travel Requirements Assistance',
                ],
                'images' => [
                    '/storage/packages/q6aoqWfmNqBbSawRCep4ONM5l94WiTicgmdLCyY9.png',
                ],
                'is_active' => true,
            ],

            [
                'name' => 'Boracay Best Deal',
                'type' => 'Flight + Hotel + Transfer + Island Hopping',
                'price' => 9999.00000000,
                'days' => 3,
                'nights' => 2,
                'min_pax' => 2,
                'valid_from' => '2026-09-01',
                'valid_to' => '2026-09-30',
                'generic_inclusions' => [
                    'Roundtrip Airfare',
                    'Roundtrip Transfer',
                    '2 Nights Hotel Accommodation (My Station Hotel)',
                    'Van and Boat Transfer',
                    'Island Hopping w/ Lunch Buffet & Kawa Bath',
                    'Terminal Fee',
                    'Environmental Fee',
                    'Taxes and Permits',
                    'Travel Coordinator',
                    'Travel Requirements Assistance',
                ],
                'images' => [
                    '/storage/packages/nVrQS2fFoYiJmsYxt850ptxPqixT2DsjmLH64Ww0.png',
                ],
                'is_active' => true,
            ],
        ];

        // NOTE: package_hotel / package_activity pivots are empty in the
        // source database, so no relations are seeded here.

        foreach ($packages as $pkg) {
            $name = $pkg['name'];
            unset($pkg['name']);

            Package::updateOrCreate(
                ['name' => $name],
                array_merge(['destination_id' => $boracay->id], $pkg)
            );
        }
    }
}
