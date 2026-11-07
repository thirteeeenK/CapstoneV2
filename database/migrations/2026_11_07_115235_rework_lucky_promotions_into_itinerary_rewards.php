<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lucky discounts become prizes won on an itinerary rather than codes
     * travelers type. Campaigns gain a win chance and a duration, their issue
     * and redemption windows merge into one validity period, and an offer that
     * held a code keeps its discount through its campaign link.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedSmallInteger('win_percent')->default(80);
            $table->unsignedSmallInteger('duration_days')->nullable();
        });
        DB::statement('UPDATE promotions SET redeem_starts_at = COALESCE(redeem_starts_at, issue_starts_at), redeem_ends_at = COALESCE(redeem_ends_at, issue_ends_at)');

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'issue_starts_at', 'issue_ends_at']);
            $table->dropColumn(['code_prefix', 'issue_starts_at', 'issue_ends_at', 'codes_per_traveler_per_day']);
            $table->index('is_active');
        });
        Schema::table('lucky_offers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
        Schema::table('promotion_redemptions', fn (Blueprint $table) => $table->dropColumn('code'));
    }

    /**
     * Restores the code columns with generated values, since the codes
     * themselves are gone.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('code_prefix', 12)->default('LUCKY');
            $table->timestampTz('issue_starts_at')->nullable();
            $table->timestampTz('issue_ends_at')->nullable();
            $table->unsignedSmallInteger('codes_per_traveler_per_day')->default(3);
        });
        DB::statement('UPDATE promotions SET issue_starts_at = redeem_starts_at, issue_ends_at = redeem_ends_at');
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('code_prefix', 12)->default(null)->change();
            $table->index(['is_active', 'issue_starts_at', 'issue_ends_at']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['win_percent', 'duration_days']);
        });

        Schema::table('lucky_offers', fn (Blueprint $table) => $table->string('code', 32)->nullable()->unique());
        DB::statement("UPDATE lucky_offers SET code = 'LUCKY-' || id WHERE promotion_id IS NOT NULL");

        Schema::table('promotion_redemptions', fn (Blueprint $table) => $table->string('code', 32)->nullable());
        DB::statement("UPDATE promotion_redemptions SET code = 'LUCKY-' || lucky_offer_id");
        Schema::table('promotion_redemptions', fn (Blueprint $table) => $table->string('code', 32)->nullable(false)->change());
    }
};
