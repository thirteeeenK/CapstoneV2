<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Transactional audit data: expected well under 1M rows for this application.
 * Rows are retained with their booking and cascade only when that booking is deleted.
 */

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('gotyme');
            $table->string('sender_reference', 50);
            $table->string('normalized_reference', 50);
            $table->decimal('claimed_amount', 12, 2);
            $table->decimal('verified_amount', 12, 2)->nullable();
            $table->string('status', 20)->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'normalized_reference'], 'booking_payments_provider_reference_unique');
            $table->index(['booking_id', 'status'], 'booking_payments_booking_status_index');
            $table->index('verified_by_admin_id', 'booking_payments_verifier_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
