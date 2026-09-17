<?php

namespace Database\Seeders;

use App\Models\DestinationModel;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $destinations = [
            [
                'name' => 'El Nido',
                'description' => 'Whispers of limestone giants and hidden lagoons. Explore secret beaches accessible only by swimming through subterranean tunnels.',
                'image' => 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&w=1000&q=80',
                'latitude' => 11.18040000,
                'longitude' => 119.39090000,
            ],
            [
                'name' => 'Boracay',
                'description' => 'Where the sun greets the softest flour-white sands on Earth. A perfect balance of island energy and serene wellness retreats.',
                'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1000&q=80',
                'latitude' => 11.96860000,
                'longitude' => 121.92300000,
            ],
        ];

        foreach ($destinations as $destination) {
            DestinationModel::updateOrCreate(
                ['name' => $destination['name']],
                [
                    'description' => $destination['description'],
                    'image' => $destination['image'],
                    'latitude' => $destination['latitude'],
                    'longitude' => $destination['longitude'],
                ]
            );
        }
    }
}
