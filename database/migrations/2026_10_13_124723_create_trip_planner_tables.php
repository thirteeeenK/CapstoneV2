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
        Schema::create('tourist_attractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->jsonb('scheduling')->nullable();
            $table->boolean('is_shown')->default(true);
            $table->index(['destination_id', 'is_shown']);
            $table->timestamps();
        });
        Schema::create('trip_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'updated_at']);
            $table->foreignId('destination_id')->constrained('destinations')->restrictOnDelete();
            $table->index('destination_id');
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('pax')->default(2);
            $table->string('mode', 12)->default('walking');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('trip_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_plan_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id');
            $table->jsonb('options');
            $table->timestamps();
            $table->unique(['trip_plan_id', 'item_type', 'item_id']);
        });
        Schema::create('trip_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->jsonb('snapshot');
            $table->timestampTz('transferred_at')->nullable();
            $table->timestamps();
            $table->index(['trip_plan_id', 'revision']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('trip_itinerary_id')->nullable()->constrained()->restrictOnDelete();
            $table->index('trip_itinerary_id');
            $table->foreignId('trip_plan_item_id')->nullable()->constrained()->nullOnDelete();
            $table->index('trip_plan_item_id');
            $table->unique(['trip_itinerary_id', 'trip_plan_item_id']);
        });
        Schema::table('bookings', fn (Blueprint $table) => $table->jsonb('trip_itineraries')->nullable());
        Schema::table('activities', fn (Blueprint $table) => $table->jsonb('scheduling')->nullable());
        Schema::table('hotels', function (Blueprint $table) {
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', fn (Blueprint $table) => $table->dropColumn(['check_in_time', 'check_out_time']));
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('scheduling'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('trip_itineraries'));
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['trip_itinerary_id', 'trip_plan_item_id']);
            $table->dropConstrainedForeignId('trip_itinerary_id');
            $table->dropConstrainedForeignId('trip_plan_item_id');
        });
        Schema::dropIfExists('trip_itineraries');
        Schema::dropIfExists('trip_plan_items');
        Schema::dropIfExists('trip_plans');
        Schema::dropIfExists('tourist_attractions');
    }
};
