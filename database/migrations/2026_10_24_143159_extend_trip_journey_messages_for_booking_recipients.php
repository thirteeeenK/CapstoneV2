<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-created guest bookings have no user account, so a journey message
     * may address the booking's saved contact email instead. Each message also
     * records the service group (trip window) it describes.
     */
    public function up(): void
    {
        Schema::table('trip_journey_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('recipient_email', 150)->nullable();
            $table->string('service_group', 120)->nullable();
            $table->string('status_reason', 60)->nullable();
            $table->index(['booking_id', 'service_group', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('trip_journey_messages')->whereNull('user_id')->delete();
        Schema::table('trip_journey_messages', function (Blueprint $table) {
            $table->dropIndex(['booking_id', 'service_group', 'created_at']);
            $table->dropColumn(['recipient_email', 'service_group', 'status_reason']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
