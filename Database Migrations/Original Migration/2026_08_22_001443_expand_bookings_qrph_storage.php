<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // payment_url holds a data URI (several KB) for QR Ph — varchar(500) is too small
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings ALTER COLUMN payment_url TYPE text');
        } else {
            Schema::table('bookings', function (Blueprint $table) {
                $table->text('payment_url')->nullable()->change();
            });
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'gateway_data')) {
                $table->jsonb('gateway_data')->nullable()->after('payment_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'gateway_data')) {
                $table->dropColumn('gateway_data');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings ALTER COLUMN payment_url TYPE varchar(500)');
        } else {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('payment_url', 500)->nullable()->change();
            });
        }
    }
};
