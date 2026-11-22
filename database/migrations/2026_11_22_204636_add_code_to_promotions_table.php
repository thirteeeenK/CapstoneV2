<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions with a code are typed by travelers at checkout; promotions
     * without one stay Lucky prizes. A code redemption has no Lucky offer.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('name');
        });

        Schema::table('promotion_redemptions', function (Blueprint $table) {
            $table->foreignId('lucky_offer_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_redemptions', function (Blueprint $table) {
            $table->foreignId('lucky_offer_id')->nullable(false)->change();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
