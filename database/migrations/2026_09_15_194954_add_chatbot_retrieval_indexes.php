<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS chat_messages_session_created_idx ON chat_messages (chat_session_id, created_at, id)');
        DB::statement('CREATE INDEX IF NOT EXISTS hotels_visible_destination_idx ON hotels (destination_id) WHERE is_shown = true AND embedding IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS rooms_visible_hotel_idx ON rooms (hotel_id) WHERE is_shown = true AND embedding IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS activities_visible_destination_idx ON activities (destination_id) WHERE is_shown = true AND embedding IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS add_ons_visible_destination_idx ON add_ons (destination_id) WHERE is_shown = true AND embedding IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS packages_active_destination_idx ON packages (destination_id) WHERE is_active = true AND embedding IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS faqs_active_sort_idx ON faqs (sort_order) WHERE is_active = true AND embedding IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS chat_messages_session_created_idx');
        DB::statement('DROP INDEX IF EXISTS hotels_visible_destination_idx');
        DB::statement('DROP INDEX IF EXISTS rooms_visible_hotel_idx');
        DB::statement('DROP INDEX IF EXISTS activities_visible_destination_idx');
        DB::statement('DROP INDEX IF EXISTS add_ons_visible_destination_idx');
        DB::statement('DROP INDEX IF EXISTS packages_active_destination_idx');
        DB::statement('DROP INDEX IF EXISTS faqs_active_sort_idx');
    }
};
