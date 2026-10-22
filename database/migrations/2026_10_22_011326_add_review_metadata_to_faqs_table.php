<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive and nullable: existing FAQs keep answering, and the missing
     * review metadata is shown to admins as a review gap.
     */
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('source_label')->nullable()->after('category');
            $table->string('source_url', 2048)->nullable()->after('source_label');
            $table->date('reviewed_at')->nullable()->after('source_url');
            $table->string('reviewed_by')->nullable()->after('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn(['source_label', 'source_url', 'reviewed_at', 'reviewed_by']);
        });
    }
};
