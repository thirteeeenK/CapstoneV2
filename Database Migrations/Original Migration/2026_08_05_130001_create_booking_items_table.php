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
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->string('item_type', 50); // Morph type ('room', 'activity', 'addon', 'package')
            $table->unsignedBigInteger('item_id');
            $table->string('item_title');
            $table->string('item_subtitle')->nullable();
            $table->string('hotel_name')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->integer('quantity')->default(1);
            $table->integer('selected_pax')->default(1);
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->integer('nights')->default(1);
            $table->decimal('subtotal', 12, 2);
            $table->json('item_snapshot')->nullable();
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
