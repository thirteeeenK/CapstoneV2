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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained('destinations')->onDelete('cascade');
            $table->string('name');
            $table->string('type')->nullable(); // e.g. "Flight + Hotel + Transfer"
            $table->decimal('price', 10, 2);
            $table->integer('days')->nullable();
            $table->integer('nights')->nullable();
            $table->integer('min_pax')->default(2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->json('generic_inclusions')->nullable(); // For simple text checkmarks
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
