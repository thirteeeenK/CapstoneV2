<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            DB::table('reviews')
                ->where('reviewable_type', 'App\\Models\\HotelModel')
                ->update(['reviewable_type' => 'hotel']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reviews')) {
            DB::table('reviews')
                ->where('reviewable_type', 'hotel')
                ->update(['reviewable_type' => 'App\\Models\\HotelModel']);
        }
    }
};
