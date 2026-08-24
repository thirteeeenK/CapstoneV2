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
            $needsSave = false;

            // Backfill base_occupancy when NULL (accessor defaults to 2, but DB stays null)
            if ($room->getAttributes()['base_occupancy'] === null) {
                $room->base_occupancy = 2;
                $needsSave = true;
            }

            // Backfill max_occupancy when NULL: fall back to legacy occupancy
            if ($room->getAttributes()['max_occupancy'] === null) {
                $room->max_occupancy = $room->getAttributes()['occupancy'] ?? $room->base_occupancy;
                $needsSave = true;
            }

            // Normalize extra_person_fee: max <= base => explicitly 0.00 (No extra guests allowed)
            if ($room->max_occupancy <= $room->base_occupancy) {
                if ((float) ($room->getAttributes()['extra_person_fee'] ?? 0) !== 0.0) {
                    $room->extra_person_fee = 0.00;
                    $needsSave = true;
                }
                if ($needsSave) {
                    $room->save();
                }

                continue;
            }

            // max > base but fee still null/zero => derive 10% of base_price rounded to nearest 100
            $currentFee = $room->getAttributes()['extra_person_fee'];
            if ($currentFee === null || (float) $currentFee === 0.0) {
                $fee = round((float) $room->base_price * 0.10 / 100) * 100;
                $room->extra_person_fee = max(100, $fee);
                $needsSave = true;
            }

            if ($needsSave) {
                $room->save();
            }
        }
    }
}
