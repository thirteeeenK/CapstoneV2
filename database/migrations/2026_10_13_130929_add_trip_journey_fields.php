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
            $table->boolean('trip_reminders_enabled')->default(true);
            $table->boolean('trip_marketing_enabled')->default(false);
        });
        Schema::table('booking_items', function (Blueprint $table) {
            $table->string('fulfillment_status', 12)->default('pending');
            $table->timestampTz('fulfilled_at')->nullable();
            $table->foreignId('fulfillment_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->index('fulfillment_admin_id');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestampTz('trip_end_override')->nullable();
        });
        Schema::create('trip_journey_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index('booking_id');
            $table->foreignId('trip_plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index('trip_plan_id');
            $table->string('dedupe_key')->unique();
            $table->string('kind', 20);
            $table->string('date_signature', 64)->nullable();
            $table->string('status', 12)->default('pending');
            $table->jsonb('payload');
            $table->timestampTz('attempted_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_journey_messages');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('trip_end_override'));
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fulfillment_admin_id');
            $table->dropColumn(['fulfillment_status', 'fulfilled_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['trip_reminders_enabled', 'trip_marketing_enabled']));
    }
};
