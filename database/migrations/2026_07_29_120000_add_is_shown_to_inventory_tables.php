<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('hotels') && !Schema::hasColumn('hotels', 'is_shown')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->boolean('is_shown')->default(true)->after('images');
            });
        }

        if (Schema::hasTable('rooms') && !Schema::hasColumn('rooms', 'is_shown')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->boolean('is_shown')->default(true)->after('images');
            });
        }

        if (Schema::hasTable('activities') && !Schema::hasColumn('activities', 'is_shown')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->boolean('is_shown')->default(true)->after('images');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hotels') && Schema::hasColumn('hotels', 'is_shown')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->dropColumn('is_shown');
            });
        }

        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'is_shown')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('is_shown');
            });
        }

        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'is_shown')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('is_shown');
            });
        }
    }
};
