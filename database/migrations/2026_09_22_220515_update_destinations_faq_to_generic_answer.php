<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace the hardcoded destination list in the destinations FAQ with a
     * generic answer. The deterministic destinations-overview handler is the
     * single source of truth for live destination names.
     */
    public function up(): void
    {
        DB::table('faqs')
            ->where('question', 'Which destinations does SunnyTrips cover?')
            ->update([
                'answer' => 'SunnyTrips covers Philippine destinations listed on our destinations page — each destination page highlights curated stays and experiences. Ask SunnyBot "what destinations are offered" for the current live list.',
                'keywords' => 'destinations, philippines, where, cover, offered',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('faqs')
            ->where('question', 'Which destinations does SunnyTrips cover?')
            ->update([
                'answer' => 'SunnyTrips focuses on Philippine destinations — including Palawan (El Nido, Coron), Boracay, Cebu, Siargao, Bohol, and more. Each destination page highlights curated stays and experiences powered by our AI recommendations.',
                'keywords' => 'destinations, palawan, el nido, boracay, cebu, siargao, bohol, philippines',
                'updated_at' => now(),
            ]);
    }
};
