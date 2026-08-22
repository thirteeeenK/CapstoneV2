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
                'destination_id' => $boracay->id,
                'name' => 'Boracay Tipid Deal',
                'type' => 'Flight + Hotel + Transfer',
                'price' => 6999.00,
                'days' => 3,
                'nights' => 2,
                'min_pax' => 2,
                'valid_from' => '2025-08-01',
                'valid_to' => '2025-12-31',
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
                'is_active' => true,
            ],
            [
                'destination_id' => $boracay->id,
                'name' => 'Boracay Sulit Deal',
                'type' => 'Flight + Hotel + Transfer + Land Tour',
                'price' => 8699.00,
                'days' => 3,
                'nights' => 2,
                'min_pax' => 2,
                'valid_from' => '2025-08-01',
                'valid_to' => '2025-12-31',
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
                'is_active' => true,
            ],
            [
                'destination_id' => $boracay->id,
                'name' => 'Boracay Best Deal',
                'type' => 'Flight + Hotel + Transfer + Island Hopping',
                'price' => 9999.00,
                'days' => 3,
                'nights' => 2,
                'min_pax' => 2,
                'valid_from' => '2025-08-01',
                'valid_to' => '2025-12-31',
                'generic_inclusions' => [
                    'Roundtrip Airfare',
                    'Roundtrip Transfer',
                    '2 Nights Hotel Accommodation',
                    'Van and Boat Transfer',
                    'Island Hopping w/ Lunch Buffet & Kawa Bath',
                    'Terminal Fee',
                    'Environmental Fee',
                    'Taxes and Permits',
                    'Travel Coordinator',
                    'Travel Requirements Assistance',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($packages as $pkg) {
            Package::updateOrCreate(
                ['name' => $pkg['name']],
                $pkg
            );
        }
    }
}
