<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Response-linked feedback categories ("wrong information", "missing
     * part", "not helpful") and the task they refer to.
     */
    public function up(): void
    {
        Schema::table('chat_feedback', function (Blueprint $table) {
            $table->string('reason', 30)->nullable()->after('helpful');
            $table->string('task_id', 20)->nullable()->after('reason');
            $table->foreignId('user_id')->nullable()->after('chat_session_id')->constrained('users')->nullOnDelete();
            $table->index('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_feedback', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropIndex(['reason']);
            $table->dropColumn(['reason', 'task_id']);
        });
    }
};
