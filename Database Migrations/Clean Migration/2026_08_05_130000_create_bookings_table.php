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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'paid', 'completed', 'rejected', 'cancelled', 'expired'])->default('pending');

            // Booking workflow timestamps
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->onDelete('set null');

            $table->decimal('total_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);

            // Admin price adjustment
            $table->decimal('admin_discount_amount', 10, 2)->default(0.00);
            $table->decimal('admin_surcharge_amount', 10, 2)->default(0.00);
            $table->text('price_adjustment_reason')->nullable();
            $table->timestamp('price_adjusted_at')->nullable();

            $table->decimal('net_amount', 12, 2);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 100)->nullable();

            // Payment gateway fields
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_reference', 150)->nullable();
            $table->string('payment_url', 500)->nullable();

            // Contact Lead Guest Information
            $table->string('contact_name', 150);
            $table->string('contact_email', 150);
            $table->string('contact_phone', 30);
            $table->text('special_requests')->nullable();
            $table->json('guest_manifest')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};