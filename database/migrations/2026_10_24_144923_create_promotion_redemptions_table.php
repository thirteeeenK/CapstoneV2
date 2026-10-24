<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One redemption per Lucky offer, ever: reserved at booking submission,
     * redeemed at the first verified payment, released (capacity returned,
     * code retired) if the unpaid request closes.
     */
    public function up(): void
    {
        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->restrictOnDelete();
            $table->foreignId('lucky_offer_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32);
            $table->string('status', 12);
            $table->unsignedBigInteger('eligible_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->jsonb('allocations');
            $table->jsonb('terms');
            $table->string('status_reason', 160)->nullable();
            $table->timestampTz('reserved_at');
            $table->timestampTz('redeemed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestamps();
            $table->index(['promotion_id', 'status']);
            $table->index(['promotion_id', 'user_id', 'status']);
            $table->index('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
    }
};
