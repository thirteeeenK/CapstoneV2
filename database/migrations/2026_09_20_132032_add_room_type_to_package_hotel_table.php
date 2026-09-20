<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('package_hotel', function (Blueprint $table) {
            $table->foreignId('room_type_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->index('room_type_id');
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS package_hotel_package_hotel_room_unique ON package_hotel (package_id, hotel_id, COALESCE(room_type_id, -1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS package_hotel_package_hotel_room_unique');
        Schema::table('package_hotel', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_type_id');
        });
    }
};
