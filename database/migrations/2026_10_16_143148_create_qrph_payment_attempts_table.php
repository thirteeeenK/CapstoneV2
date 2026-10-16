<?php

declare(strict_types=1);

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
        Schema::create('qrph_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('intent_id', 150)->nullable()->unique();
            $table->string('method_id', 150)->nullable();
            $table->unsignedSmallInteger('method_generation')->default(1);
            $table->string('qr_code_id', 150)->nullable()->index();
            $table->string('payment_id', 150)->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('PHP');
            $table->boolean('livemode');
            $table->string('status', 24)->default('creating');
            $table->text('qr_image')->nullable();
            $table->text('test_url')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampsTz();
            $table->index(['booking_id', 'id']);
            $table->index(['status', 'last_checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qrph_payment_attempts');
    }
};
