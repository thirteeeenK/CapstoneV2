<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce a single ACTIVE support ticket per chat session. A partial
     * unique index lets closed (resolved/returned) inquiries free the slot
     * so the user can request a new agent afterwards.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX support_inquiries_single_active_per_session '.
            'ON support_inquiries (chat_session_id) '.
            "WHERE status IN ('PENDING_ASSIGNMENT', 'ASSIGNED')"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('support_inquiries', function ($table) {
                // no-op; drop via statement
            });
            DB::statement('DROP INDEX IF EXISTS support_inquiries_single_active_per_session');
        }
    }
};
