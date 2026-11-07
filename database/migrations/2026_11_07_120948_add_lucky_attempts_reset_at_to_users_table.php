<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an admin last reset a traveler's Lucky attempts; only attempts
     * after this moment count, so no saved itinerary has to be deleted. No time
     * zone, matching lucky_offers.created_at, which it is compared against.
     */
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('lucky_attempts_reset_at')->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('lucky_attempts_reset_at'));
    }
};
