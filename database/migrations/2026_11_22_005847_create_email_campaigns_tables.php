<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin promotional emails: a campaign is the message and its audience,
     * a run is one send of it (once for a manual or scheduled campaign, once
     * per firing for an automatic one), and recipients are its delivery log.
     */
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('subject', 150);
            $table->string('preheader', 150)->nullable();
            $table->string('headline', 150);
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_target', 20)->default('lucky');
            $table->unsignedBigInteger('cta_target_id')->nullable();
            $table->string('cta_path', 255)->nullable();
            $table->string('send_mode', 12)->default('manual');
            $table->string('trigger', 30)->nullable();
            $table->jsonb('audience')->nullable();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('status', 12)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['send_mode', 'trigger', 'status']);
        });

        Schema::create('email_campaign_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('run_key', 80);
            $table->jsonb('context')->nullable();
            $table->string('status', 12)->default('sending');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_id', 'run_key']);
            $table->index('status');
        });

        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('status', 12)->default('pending');
            $table->string('status_reason', 40)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_run_id', 'user_id']);
            $table->index(['status', 'id']);
            $table->index(['user_id', 'status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
        Schema::dropIfExists('email_campaign_runs');
        Schema::dropIfExists('email_campaigns');
    }
};
