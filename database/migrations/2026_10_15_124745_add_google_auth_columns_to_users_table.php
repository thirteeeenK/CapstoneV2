<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');

            // Google-only accounts never pick a password. Postgres allows any
            // number of NULLs under a unique index, so google_id stays unique
            // while password-based accounts keep a NULL google_id.
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar']);
        });

        // Google-only rows hold a NULL password and would violate the restored
        // NOT NULL constraint. Give them a hash nothing can match, so they are
        // locked out rather than silently granted an empty password.
        DB::table('users')->whereNull('password')->update([
            'password' => Hash::make(Str::random(64)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
