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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('session_token')->nullable()->index();
            $table->string('item_type'); // Morph class or type (e.g. App\Models\RoomType, App\Models\ActivityModel, etc.)
            $table->unsignedBigInteger('item_id');
            $table->integer('quantity')->default(1);
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->integer('selected_pax')->default(1);
            $table->boolean('is_selected')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
