<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('hotel_name');
            //foreign key constrained sa destinations table
            $table->foreignId('destination_id')->constrained('destinations')->onDelete('cascade');
            $table->string('type');
            $table->json('vibe_tags')->nullable();
            $table->json('featured_amenities')->nullable();
            $table->text('hotel_description');
            $table->string('specific_address'); // e.g., "Station 2, White Beach"
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('images')->nullable();
            $table->boolean('is_shown')->default(true);
            $table->vector('embedding', 3072)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};