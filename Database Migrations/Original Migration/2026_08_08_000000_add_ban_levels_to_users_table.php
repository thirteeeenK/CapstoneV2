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
        Schema::table('users', function (Blueprint $table) {
            $table->string('ban_level')->nullable()->after('ban_reason');
            $table->timestamp('banned_at')->nullable()->after('ban_level');
            $table->timestamp('ban_expires_at')->nullable()->after('banned_at');
        });

        // Backfill existing boolean bans as permanent bans so nothing is lost.
        DB::table('users')
            ->where('is_banned', true)
            ->update([
                'ban_level' => 'permanent',
                'banned_at' => now(),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_banned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_banned')->default(false)->after('remember_token');
        });

        // Restore the boolean from the leveled state (expired temporary bans are not banned).
        DB::table('users')
            ->where('ban_level', 'permanent')
            ->orWhere(function ($q) {
                $q->where('ban_level', 'temporary')
                    ->where('ban_expires_at', '>', now());
            })
            ->update(['is_banned' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ban_level', 'banned_at', 'ban_expires_at']);
        });
    }
};
