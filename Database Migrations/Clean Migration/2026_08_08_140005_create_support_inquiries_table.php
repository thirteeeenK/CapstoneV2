<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->string('status')->default('PENDING_ASSIGNMENT');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('returned_to_ai_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'assigned_admin_id']);
        });

        // Enforce a single ACTIVE support ticket per chat session. A partial
        // unique index lets closed (resolved/returned) inquiries free the slot
        // so the user can request a new agent afterwards.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX support_inquiries_single_active_per_session '.
                'ON support_inquiries (chat_session_id) '.
                "WHERE status IN ('PENDING_ASSIGNMENT', 'ASSIGNED')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_inquiries');
    }
};
