<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-defined Lucky promotion campaigns. Amounts are integer centavos;
     * a campaign starts inactive so no discount is promised until an admin
     * configures and enables it.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('is_active')->default(false);
            $table->string('code_prefix', 12);
            $table->string('discount_type', 10);
            $table->unsignedSmallInteger('percent_off')->nullable();
            $table->unsignedBigInteger('fixed_off_cents')->nullable();
            $table->unsignedBigInteger('max_discount_cents')->nullable();
            $table->unsignedBigInteger('min_eligible_cents')->default(0);
            $table->timestampTz('issue_starts_at')->nullable();
            $table->timestampTz('issue_ends_at')->nullable();
            $table->timestampTz('redeem_starts_at')->nullable();
            $table->timestampTz('redeem_ends_at')->nullable();
            $table->date('travel_starts_on')->nullable();
            $table->date('travel_ends_on')->nullable();
            $table->jsonb('destination_ids')->nullable();
            $table->unsignedSmallInteger('min_nights')->nullable();
            $table->unsignedSmallInteger('max_nights')->nullable();
            $table->unsignedSmallInteger('min_pax')->nullable();
            $table->unsignedSmallInteger('max_pax')->nullable();
            $table->unsignedInteger('total_redemption_limit')->nullable();
            $table->unsignedInteger('per_traveler_limit')->default(1);
            $table->unsignedSmallInteger('codes_per_traveler_per_day')->default(3);
            $table->unsignedSmallInteger('offer_valid_hours')->default(72);
            $table->unsignedSmallInteger('award_weight')->default(1);
            $table->text('terms')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'issue_starts_at', 'issue_ends_at']);
        });
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT promotions_discount_shape CHECK ((discount_type = 'percent' AND percent_off BETWEEN 1 AND 100 AND fixed_off_cents IS NULL) OR (discount_type = 'fixed' AND fixed_off_cents > 0 AND percent_off IS NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
