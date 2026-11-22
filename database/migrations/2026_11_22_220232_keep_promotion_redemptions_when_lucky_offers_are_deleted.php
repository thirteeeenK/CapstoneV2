<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting a traveler cascades to their Lucky offers. A redemption is a
     * booking's accounting record, so it stays (with its terms snapshot) and
     * only loses its link to the deleted offer instead of blocking the delete.
     */
    public function up(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table) {
            $table->dropForeign(['lucky_offer_id']);
            $table->foreign('lucky_offer_id')->references('id')->on('lucky_offers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table) {
            $table->dropForeign(['lucky_offer_id']);
            $table->foreign('lucky_offer_id')->references('id')->on('lucky_offers')->restrictOnDelete();
        });
    }
};
