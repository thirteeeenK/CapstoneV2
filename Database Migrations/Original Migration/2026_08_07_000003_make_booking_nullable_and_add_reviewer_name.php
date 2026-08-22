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
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('reviewer_name')->nullable()->after('user_id');
        });

        // Make booking_id nullable (manual admin reviews have no booking).
        // PostgreSQL: UNIQUE constraint still works — multiple NULLs are allowed.
        DB::statement('ALTER TABLE reviews ALTER COLUMN booking_id DROP NOT NULL');

        // Make user_id nullable (manual admin reviews have no user record).
        DB::statement('ALTER TABLE reviews ALTER COLUMN user_id DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE reviews ALTER COLUMN user_id SET NOT NULL');
        DB::statement('ALTER TABLE reviews ALTER COLUMN booking_id SET NOT NULL');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('reviewer_name');
        });
    }
};
