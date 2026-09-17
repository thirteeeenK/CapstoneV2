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
        Schema::create('recommendation_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // One token per dashboard render; impression row = session denominator.
            $table->string('session_token', 64);
            // ai | default (control group)
            $table->string('mode', 16);
            // impression | hotel | activity
            $table->string('entity_type', 16);
            $table->unsignedBigInteger('entity_id')->nullable();
            // 1-based rank on the card at click time (null for impressions)
            $table->unsignedTinyInteger('rank')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'session_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_hits');
    }
};
