<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a traveler last gave or withdrew consent to marketing email, kept
     * beside trip_marketing_enabled as the Data Privacy Act consent record.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamp('marketing_unsubscribed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marketing_consent_at', 'marketing_unsubscribed_at']);
        });
    }
};
