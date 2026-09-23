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
        foreach (['hotels', 'rooms', 'activities', 'packages', 'add_ons', 'faqs'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('embedding_source_hash')->nullable()->after('embedding');
                    $table->string('embedding_model')->nullable()->after('embedding_source_hash');
                    $table->timestamp('embedded_at')->nullable()->after('embedding_model');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['hotels', 'rooms', 'activities', 'packages', 'add_ons', 'faqs'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn(['embedding_source_hash', 'embedding_model', 'embedded_at']);
                });
            }
        }
    }
};
