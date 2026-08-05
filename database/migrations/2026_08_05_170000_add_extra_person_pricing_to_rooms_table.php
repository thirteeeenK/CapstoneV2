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
            $table->integer('base_occupancy')->default(2)->nullable()->after('occupancy');
            $table->integer('max_occupancy')->nullable()->after('base_occupancy');
            $table->decimal('extra_person_fee', 10, 2)->default(0.00)->after('base_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['base_occupancy', 'max_occupancy', 'extra_person_fee']);
        });
    }
};
