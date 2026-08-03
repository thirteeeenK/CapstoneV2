<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DestinationModel;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $destinations = [
            ['name' => 'Boracay'],
            ['name' => 'El Nido'],
        ];

        foreach ($destinations as $destination) {
            DestinationModel::firstOrCreate(
                ['name' => $destination['name']],
                $destination
            );
        }
    }
}