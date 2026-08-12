<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-item availability verification fields to booking items.
     */
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->enum('availability_status', ['pending', 'available', 'unavailable'])
                ->default('pending')
                ->after('item_snapshot');
            $table->text('admin_note')->nullable()->after('availability_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn(['availability_status', 'admin_note']);
        });
    }
};
