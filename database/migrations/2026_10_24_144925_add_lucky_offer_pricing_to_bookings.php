<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotional discounts are kept apart from passenger-category
     * (discount_amount) and admin adjustments, with each booking item's share
     * recorded so excluded or refunded items stay reconcilable.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('promotion_discount_amount', 12, 2)->default(0);
        });
        Schema::table('booking_items', function (Blueprint $table) {
            $table->decimal('promotion_discount_amount', 12, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', fn (Blueprint $table) => $table->dropColumn('promotion_discount_amount'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('promotion_discount_amount'));
    }
};
