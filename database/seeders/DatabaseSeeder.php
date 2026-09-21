<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('12345678'),
            ]
        );

        $this->call([
            DestinationSeeder::class,
            AdminSeeder::class,
            OnboardingOptionSeeder::class,
            HotelSeeder::class,
            // Runs BEFORE the room seeders on purpose: it only backfills
            // occupancy/fee gaps on pre-existing rows. The room seeders below
            // write exact live values (incl. explicit 0.00 fees and null
            // max_occupancy), so they must have the last word.
            RoomExtraPersonFeeSeeder::class,
            RoomSeeder::class,
            ElNidoRoomSeeder::class,
            ActivitySeeder::class,
            ElNidoActivitySeeder::class,
            ActivityCoordinatesSeeder::class,
            LegalDocumentsSeeder::class,
            AddOnSeeder::class,
            PackageSeeder::class,
            PassengerCategoryRuleSeeder::class,
            FaqSeeder::class,
            // Sole review source: 295 blind sentiment-eval reviews. The old
            // demo pools (ReviewSeeder 55, HotelReviewSeeder md) are manual
            // --class runs only, so bare db:seed never pollutes prod again.
            SentimentEvalSeeder::class,
        ]);
    }
}
