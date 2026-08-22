<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomExtraPersonFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoomType::all() as $room) {
            if ($room->max_occupancy <= $room->base_occupancy) {
                continue;
            }

            $fee = round((float) $room->base_price * 0.10 / 100) * 100;

            $room->extra_person_fee = $fee;
            $room->save();
        }
    }
}
