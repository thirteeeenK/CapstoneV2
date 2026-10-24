<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A server-generated Lucky itinerary, persisted before it is shown so its
     * selection, owner and any issued code can be trusted at acceptance and
     * checkout. The basket lines it was accepted into point back to it.
     */
    public function up(): void
    {
        Schema::create('lucky_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32)->nullable()->unique();
            $table->unsignedInteger('promotion_revision')->nullable();
            $table->jsonb('terms')->nullable();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->unsignedBigInteger('room_id');
            $table->jsonb('activity_ids');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('pax');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->decimal('max_budget', 12, 2);
            $table->boolean('budget_exceeded')->default(false);
            $table->unsignedBigInteger('quoted_total_cents');
            $table->unsignedBigInteger('estimated_discount_cents')->default(0);
            $table->string('selection_signature', 64);
            $table->uuid('lucky_group_id')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['promotion_id', 'user_id', 'created_at']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('lucky_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->index('lucky_offer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('lucky_offer_id'));
        Schema::dropIfExists('lucky_offers');
    }
};
