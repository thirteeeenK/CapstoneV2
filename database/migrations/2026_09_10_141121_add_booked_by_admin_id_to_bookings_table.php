<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('booked_by_admin_id')->nullable()->after('reviewed_by_admin_id')->constrained('admins')->onDelete('set null');
            $table->string('booking_source', 30)->default('customer_web')->after('booked_by_admin_id');
            $table->boolean('is_walk_in')->default(false)->after('booking_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['booked_by_admin_id']);
            $table->dropColumn(['booked_by_admin_id', 'booking_source', 'is_walk_in']);
        });
    }
};
