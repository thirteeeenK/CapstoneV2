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
        Schema::table('destinations', function (Blueprint $table) {
            if (!Schema::hasColumn('destinations', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('image');
            }
            if (!Schema::hasColumn('destinations', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            if (Schema::hasColumn('destinations', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('destinations', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }
};
