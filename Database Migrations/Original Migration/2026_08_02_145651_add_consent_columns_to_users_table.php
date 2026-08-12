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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->timestamp('ai_disclosure_accepted_at')->nullable();

            $table->string('terms_version', 10)->nullable();
            $table->string('privacy_version', 10)->nullable();
            $table->string('ai_disclosure_version', 10)->nullable();

            $table->string('consent_ip_address', 45)->nullable();
            $table->string('consent_user_agent', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'terms_accepted_at',
                'privacy_accepted_at',
                'ai_disclosure_accepted_at',
                'terms_version',
                'privacy_version',
                'ai_disclosure_version',
                'consent_ip_address',
                'consent_user_agent',
            ]);
        });
    }
};
