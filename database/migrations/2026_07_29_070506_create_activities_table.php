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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained('destinations')->onDelete('cascade');
            $table->string('activity_name');
            $table->string('category'); // e.g., Island Hopping, Land Tour, Water Activity, Diving, Adventure, Sailing, Other
            $table->string('activity_level'); // e.g., Relaxing, Sightseeing, Adventure, Extreme, Underwater
            $table->string('rate'); // e.g., ₱850/person, ₱2,000, ₱200–₱300/person
            $table->string('duration')->nullable(); // e.g., 3 Hours, Half Day, Full Day, 15 Mins
            $table->string('capacity')->nullable(); // e.g., 1–6 Pax, Max 10 Guests
            $table->text('requirements')->nullable(); // e.g., Must bring extra clothes, Age 12+
            $table->string('ideal_for')->nullable(); // Target audience / ideal participants
            $table->json('vibe_tags')->nullable();
            $table->text('description')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('itinerary')->nullable();
            $table->text('notes')->nullable(); // Inclusions / details
            $table->json('images')->nullable();
            $table->vector('embedding', 3072)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
