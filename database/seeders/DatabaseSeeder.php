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

        $this->call(DestinationSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(HotelSeeder::class);
        $this->call(RoomSeeder::class);
        $this->call(ElNidoRoomSeeder::class);
        $this->call(ActivitySeeder::class);
        $this->call(LegalDocumentsSeeder::class);
    }
}
