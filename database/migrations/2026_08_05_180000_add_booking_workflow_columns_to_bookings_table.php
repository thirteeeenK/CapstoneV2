<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add booking workflow columns and rebuild the status enum
     * (Postgres enums cannot have values dropped, so the type is recreated).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->timestamp('payment_deadline')->nullable()->after('approved_at');
            $table->timestamp('paid_at')->nullable()->after('payment_deadline');
            $table->timestamp('rejected_at')->nullable()->after('paid_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->timestamp('expired_at')->nullable()->after('cancellation_reason');
            $table->text('admin_notes')->nullable()->after('expired_at');
            $table->foreignId('reviewed_by_admin_id')->nullable()
                ->after('admin_notes')->constrained('admins')->onDelete('set null');
            $table->string('gateway', 20)->nullable()->after('payment_reference');
            $table->string('gateway_reference', 150)->nullable()->after('gateway');
            $table->string('payment_url', 500)->nullable()->after('gateway_reference');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE bookings DROP CONSTRAINT bookings_status_check");
            DB::statement(
                "ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'approved', 'paid', 'completed', 'rejected', 'cancelled', 'expired'))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_admin_id']);
            $table->dropColumn([
                'approved_at',
                'payment_deadline',
                'paid_at',
                'rejected_at',
                'rejection_reason',
                'cancelled_at',
                'cancellation_reason',
                'expired_at',
                'admin_notes',
                'reviewed_by_admin_id',
                'gateway',
                'gateway_reference',
                'payment_url',
            ]);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE bookings DROP CONSTRAINT bookings_status_check");
            DB::statement(
                "ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed'))"
            );
        }
    }
};
