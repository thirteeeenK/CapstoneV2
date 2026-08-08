<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE packages ALTER COLUMN embedding TYPE vector(3072) USING (CASE WHEN embedding IS NULL OR embedding = '' THEN NULL ELSE embedding::vector END)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE packages ALTER COLUMN embedding TYPE text");
    }
};
