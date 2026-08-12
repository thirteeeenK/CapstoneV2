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
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('admin_discount_amount', 10, 2)->default(0.00)->after('tax_amount');
            $table->decimal('admin_surcharge_amount', 10, 2)->default(0.00)->after('admin_discount_amount');
            $table->text('price_adjustment_reason')->nullable()->after('admin_surcharge_amount');
            $table->timestamp('price_adjusted_at')->nullable()->after('price_adjustment_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['admin_discount_amount', 'admin_surcharge_amount', 'price_adjustment_reason', 'price_adjusted_at']);
        });
    }
};
