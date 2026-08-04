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
            if (!Schema::hasColumn('destinations', 'region')) {
                $table->string('region')->nullable()->after('name');
            }
            if (!Schema::hasColumn('destinations', 'description')) {
                $table->text('description')->nullable()->after('region');
            }
            if (!Schema::hasColumn('destinations', 'image')) {
                $table->string('image')->nullable()->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            if (Schema::hasColumn('destinations', 'region')) {
                $table->dropColumn('region');
            }
            if (Schema::hasColumn('destinations', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('destinations', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};
