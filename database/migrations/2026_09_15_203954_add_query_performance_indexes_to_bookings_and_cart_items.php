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
            $table->index(['status', 'payment_deadline'], 'bookings_status_payment_deadline_index');
            $table->index(['user_id', 'status'], 'bookings_user_id_status_index');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('user_id', 'cart_items_user_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_status_payment_deadline_index');
            $table->dropIndex('bookings_user_id_status_index');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_user_id_index');
        });
    }
};
