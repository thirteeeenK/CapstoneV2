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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels', 'id')->onDelete('cascade');
            $table->integer('total_rooms')->default(1);
            $table->string('room_name');
            $table->text('description')->nullable();
            $table->string('view_type')->nullable();
            $table->string('ideal_for')->nullable();
            $table->string('ideal_guest')->nullable();
            $table->text('additional_notes')->nullable();
            $table->integer('occupancy')->nullable();
            $table->integer('base_occupancy')->default(2)->nullable();
            $table->integer('max_occupancy')->nullable();
            $table->string('bed_configuration')->nullable();
            $table->string('room_size')->nullable();
            $table->text('room_amenities');
            $table->decimal('base_price', 10, 2);
            $table->decimal('extra_person_fee', 10, 2)->default(0.00);
            $table->json('images')->nullable();
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
        Schema::dropIfExists('rooms');
    }
};
