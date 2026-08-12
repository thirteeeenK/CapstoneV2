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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number', 15)->nullable();
            $table->string('address')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Chatbot moderation
            $table->unsignedInteger('chatbot_flag_count')->default(0);
            $table->string('ban_reason')->nullable();

            // Leveled bans (replaces the legacy boolean is_banned)
            $table->string('ban_level')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('ban_expires_at')->nullable();

            // Consent tracking
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->timestamp('ai_disclosure_accepted_at')->nullable();

            $table->string('terms_version', 10)->nullable();
            $table->string('privacy_version', 10)->nullable();
            $table->string('ai_disclosure_version', 10)->nullable();

            $table->string('consent_ip_address', 45)->nullable();
            $table->string('consent_user_agent', 255)->nullable();

            $table->vector('preferences_embedding', 3072)->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};