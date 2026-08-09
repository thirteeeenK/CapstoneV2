<?php

namespace Database\Seeders;

use App\Models\AdminModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('EMAIL');
        if (!$email) {
            $this->command->warn('Skipped admin seed — EMAIL env var not set.');
            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::random(16);
        if (!env('ADMIN_PASSWORD')) {
            $this->command->info("Admin created with random password: {$password}");
        }

        AdminModel::firstOrCreate(['email' => $email], [
            'name' => 'Administrator',
            'password' => Hash::make($password),
        ]);
    }
}