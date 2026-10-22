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
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->string('source', 20)->default('manual');
            $table->string('status', 20)->default('dated');
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->jsonb('context')->nullable();
            $table->uuid('source_suggestion_uuid')->nullable();
            $table->unique(['user_id', 'source_suggestion_uuid']);
        });
        DB::statement('ALTER TABLE trip_plans ADD CONSTRAINT trip_plan_date_pair CHECK ((start_date IS NULL AND end_date IS NULL) OR (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date > start_date))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('trip_plans')->whereNull('start_date')->exists()) {
            throw new RuntimeException('Export or assign dates to undated plans before rolling back this migration.');
        }
        DB::statement('ALTER TABLE trip_plans DROP CONSTRAINT trip_plan_date_pair');
        Schema::table('trip_plans', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'source_suggestion_uuid']);
            $table->dropColumn(['source', 'status', 'duration_days', 'context', 'source_suggestion_uuid']);
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
