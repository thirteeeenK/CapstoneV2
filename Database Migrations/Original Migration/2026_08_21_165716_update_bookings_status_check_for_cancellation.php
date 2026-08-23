<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
            DB::statement(
                "ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'approved', 'paid', 'completed', 'rejected', 'cancelled', 'expired', 'cancellation_requested', 'cancellation_denied'))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
            DB::statement(
                "ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'approved', 'paid', 'completed', 'rejected', 'cancelled', 'expired'))"
            );
        }
    }
};
