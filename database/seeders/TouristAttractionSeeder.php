<?php

namespace Database\Seeders;

use App\Models\DestinationModel;
use App\Models\TouristAttraction;
use Illuminate\Database\Seeder;

class TouristAttractionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $destination = DestinationModel::query()->where('name', 'Boracay')->first();
        if (! $destination) {
            return;
        }
        foreach (["Willy's Rock", 'Puka Shell Beach', "D'Mall Boracay"] as $name) {
            TouristAttraction::firstOrCreate(['destination_id' => $destination->id, 'name' => $name], [
                'description' => 'Self-guided visit. Entrance coordinates and opening hours must be verified by the administrator before route scheduling.',
                'is_shown' => true,
            ]);
        }
    }
}
