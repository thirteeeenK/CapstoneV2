<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_plan_version')->nullable();
            $table->string('payment_plan', 20)->nullable();
            $table->string('payment_environment', 8)->nullable();
            $table->jsonb('payment_policy')->nullable();
            $table->bigInteger('deposit_required_cents')->nullable();
            $table->timestampTz('deposit_secured_at')->nullable();
            $table->timestampTz('balance_due_at')->nullable();
            $table->timestampTz('balance_overdue_at')->nullable();
            $table->index(['status', 'balance_due_at']);
        });
        Schema::create('booking_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 12);
            $table->bigInteger('amount');
            $table->char('currency', 3)->default('PHP');
            $table->string('gateway', 20);
            $table->boolean('livemode');
            $table->bigInteger('quote_amount');
            $table->string('status', 30)->default('creating');
            $table->string('provider_reference', 150)->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestampTz('due_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
            $table->index(['booking_id', 'id']);
            $table->index(['status', 'updated_at']);
            $table->unique(['gateway', 'livemode', 'provider_reference'], 'payment_request_provider_unique');
        });
        DB::statement("CREATE UNIQUE INDEX payment_request_one_active ON booking_payment_requests (booking_id) WHERE status IN ('creating', 'pending', 'awaiting_verification', 'processing', 'review_required')");
        DB::statement('ALTER TABLE booking_payment_requests ADD CONSTRAINT payment_request_amount_positive CHECK (amount > 0 AND quote_amount > 0)');
        Schema::create('booking_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_payment_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('original_transaction_id')->nullable()->constrained('booking_payment_transactions')->restrictOnDelete();
            $table->string('gateway', 20);
            $table->boolean('livemode');
            $table->string('source_reference', 160);
            $table->string('kind', 12)->default('receipt');
            $table->bigInteger('amount');
            $table->char('currency', 3)->default('PHP');
            $table->boolean('allocated')->default(true);
            $table->timestampTz('occurred_at');
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['gateway', 'livemode', 'source_reference'], 'payment_transaction_source_unique');
            $table->index(['booking_id', 'id']);
            $table->index('booking_payment_request_id');
            $table->index('original_transaction_id');
            $table->index('verified_by_admin_id');
            $table->index(['livemode', 'occurred_at']);
        });
        DB::statement('ALTER TABLE booking_payment_transactions ADD CONSTRAINT payment_transaction_amount_positive CHECK (amount > 0)');
        foreach (['qrph_payment_attempts', 'booking_payments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('booking_payment_request_id')->nullable()->constrained()->restrictOnDelete();
                $table->index('booking_payment_request_id');
            });
        }
        Schema::create('booking_payment_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('dedupe_key', 180)->unique();
            $table->string('kind', 30);
            $table->foreignId('booking_payment_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['sent_at', 'id']);
            $table->index('booking_id');
            $table->index('booking_payment_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payment_notices');
        foreach (['qrph_payment_attempts', 'booking_payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('booking_payment_request_id'));
        }
        Schema::dropIfExists('booking_payment_transactions');
        Schema::dropIfExists('booking_payment_requests');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'balance_due_at']);
            $table->dropColumn(['payment_plan_version', 'payment_plan', 'payment_environment', 'payment_policy', 'deposit_required_cents', 'deposit_secured_at', 'balance_due_at', 'balance_overdue_at']);
        });
    }
};
