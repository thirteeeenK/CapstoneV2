<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Drop the one-per-booking unique constraint
            $table->dropUnique(['booking_id']);

            // Add booking_item_id for per-item review tracking
            $table->foreignId('booking_item_id')->nullable()->constrained('booking_items')->onDelete('cascade')->after('booking_id');

            // One review per specific booking item (nullable for admin/manual reviews)
            $table->unique(['booking_id', 'booking_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['booking_id', 'booking_item_id']);
            $table->dropForeign(['booking_item_id']);
            $table->dropColumn('booking_item_id');

            // Restore the one-per-booking unique constraint
            $table->unique(['booking_id']);
        });
    }
};
