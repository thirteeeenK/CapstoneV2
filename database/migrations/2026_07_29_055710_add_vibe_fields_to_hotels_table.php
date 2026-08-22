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
        Schema::table('hotels', function (Blueprint $table) {
            $table->json('vibe_tags')->nullable()->after('type');
            $table->string('target_audience')->nullable()->after('vibe_tags');
            $table->json('featured_amenities')->nullable()->after('target_audience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['vibe_tags', 'target_audience', 'featured_amenities']);
        });
    }
};
