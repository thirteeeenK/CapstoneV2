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
        Schema::create('ip_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('ban_level', 20);
            $table->string('reason', 255)->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('ip_address');
            $table->index('ban_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_bans');
    }
};
