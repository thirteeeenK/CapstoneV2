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
        Schema::create('passenger_category_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category_name')->unique(); // e.g. 'Adult', 'Student', 'Senior Citizen', 'PWD', 'Child', 'Infant', 'Foreigner'
            $table->string('display_label');            // e.g. 'Student', 'Foreign Tourist'
            $table->enum('adjustment_type', ['discount', 'surcharge', 'none'])->default('none');
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passenger_category_rules');
    }
};
