<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_plans', function (Blueprint $table) {
            $table->string('session_token', 64)->nullable();
            $table->index(['session_token', 'user_id']);
        });
        // Raw DDL so the existing foreign key and indexes survive; a Blueprint
        // change() rebuilds the column and drops them.
        DB::statement('ALTER TABLE trip_plans ALTER COLUMN user_id DROP NOT NULL');
        // Postgres treats NULLs as distinct, so unique(user_id, source_suggestion_uuid)
        // cannot dedupe guest saves. A partial index covers the guest half.
        DB::statement('CREATE UNIQUE INDEX trip_plans_guest_suggestion_unique ON trip_plans (session_token, source_suggestion_uuid) WHERE user_id IS NULL AND source_suggestion_uuid IS NOT NULL');
        DB::statement('ALTER TABLE trip_plans ADD CONSTRAINT trip_plan_owner CHECK (user_id IS NOT NULL OR session_token IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('trip_plans')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Claim or delete guest trip plans before rolling back this migration.');
        }
        DB::statement('ALTER TABLE trip_plans DROP CONSTRAINT trip_plan_owner');
        DB::statement('DROP INDEX trip_plans_guest_suggestion_unique');
        DB::statement('ALTER TABLE trip_plans ALTER COLUMN user_id SET NOT NULL');
        Schema::table('trip_plans', function (Blueprint $table) {
            $table->dropIndex(['session_token', 'user_id']);
            $table->dropColumn('session_token');
        });
    }
};
