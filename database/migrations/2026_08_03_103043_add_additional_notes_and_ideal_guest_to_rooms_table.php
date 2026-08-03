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
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'ideal_guest')) {
                $table->string('ideal_guest')->nullable();
            }
            if (!Schema::hasColumn('rooms', 'additional_notes')) {
                $table->text('additional_notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'ideal_guest')) {
                $table->dropColumn('ideal_guest');
            }
            if (Schema::hasColumn('rooms', 'additional_notes')) {
                $table->dropColumn('additional_notes');
            }
        });
    }
};
