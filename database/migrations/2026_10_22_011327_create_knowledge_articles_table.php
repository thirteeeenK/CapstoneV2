<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Curated, non-bookable travel guidance (access, transport, dining...).
     * The embedding columns mirror the other embeddable tables so a stale
     * vector is detectable from embedding_source_hash.
     */
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('category', 50);
            $table->foreignId('destination_id')->nullable()->constrained('destinations')->nullOnDelete();
            $table->string('source_label');
            $table->string('source_url', 2048)->nullable();
            $table->date('reviewed_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('reviewed_by')->nullable();
            $table->vector('embedding', 3072)->nullable();
            $table->string('embedding_source_hash')->nullable();
            $table->string('embedding_model')->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'destination_id']);
            $table->index('destination_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
