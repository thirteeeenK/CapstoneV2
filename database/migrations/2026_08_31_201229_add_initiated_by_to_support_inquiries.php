<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_inquiries', function (Blueprint $table) {
            $table->string('initiated_by')->default('user')->after('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Fix partial index typo: 'ASSIGNED' never occurs, should be 'HUMAN_SUPPORT_ACTIVE'.
            DB::statement('DROP INDEX IF EXISTS support_inquiries_single_active_per_session');
            DB::statement(
                'CREATE UNIQUE INDEX support_inquiries_single_active_per_session '.
                'ON support_inquiries (chat_session_id) '.
                "WHERE status IN ('PENDING_ASSIGNMENT', 'HUMAN_SUPPORT_ACTIVE')"
            );
            // Help latestForUser + admin poll by (user_id, status).
            DB::statement('CREATE INDEX IF NOT EXISTS support_inquiries_user_status_idx ON support_inquiries (user_id, status)');
        }
    }

    public function down(): void
    {
        Schema::table('support_inquiries', function (Blueprint $table) {
            $table->dropColumn('initiated_by');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS support_inquiries_single_active_per_session');
            DB::statement(
                'CREATE UNIQUE INDEX support_inquiries_single_active_per_session '.
                'ON support_inquiries (chat_session_id) '.
                "WHERE status IN ('PENDING_ASSIGNMENT', 'ASSIGNED')"
            );
            DB::statement('DROP INDEX IF EXISTS support_inquiries_user_status_idx');
        }
    }
};
