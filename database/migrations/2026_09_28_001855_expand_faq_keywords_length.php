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
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('keywords', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('faqs')->whereRaw('char_length(keywords) > 255')->exists()) {
            throw new RuntimeException('Cannot reduce faqs.keywords to 255 characters while longer values exist.');
        }

        Schema::table('faqs', function (Blueprint $table) {
            $table->string('keywords', 255)->nullable()->change();
        });
    }
};
