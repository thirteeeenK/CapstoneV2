<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rebooking is a new request; this link only records which earlier
     * booking it repeats, for audit. Nothing (payments, credits, promotions)
     * transfers through it.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('rebooked_from_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->index('rebooked_from_booking_id');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('rebooked_from_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('rebooked_from_booking_id'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropConstrainedForeignId('rebooked_from_booking_id'));
    }
};
