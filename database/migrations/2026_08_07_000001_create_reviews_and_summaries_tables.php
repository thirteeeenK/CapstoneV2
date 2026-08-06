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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // One verified review per completed booking
            $table->foreignId('booking_id')->unique()->constrained('bookings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Polymorphic target (HotelModel, RoomType, ActivityModel, Package)
            $table->string('reviewable_type');
            $table->unsignedBigInteger('reviewable_id');

            // Direct relational FK columns for ultra-fast filtering/indexing
            $table->unsignedBigInteger('hotel_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();

            $table->smallInteger('rating')->default(5);
            $table->text('comment');

            // AI sentiment analysis outputs
            $table->string('sentiment', 20)->default('neutral'); // positive | neutral | negative
            $table->decimal('sentiment_score', 5, 4)->default(0.5000);
            $table->jsonb('extracted_keywords')->default('[]'); // e.g. ["spacious room", "clean", "slow wifi"]

            $table->boolean('is_verified_booking')->default(true);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index(['hotel_id', 'room_id']);
            $table->index('activity_id');
            $table->index('package_id');
            $table->index(['rating', 'sentiment']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)'
            );
        }

        Schema::create('review_summaries', function (Blueprint $table) {
            $table->id();

            // Polymorphic summarizable entity, NULL id = platform-wide overall summary
            $table->string('summarizable_type');
            $table->unsignedBigInteger('summarizable_id')->nullable();

            $table->unsignedInteger('total_reviews')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->decimal('positive_percentage', 5, 2)->default(0.00);
            $table->decimal('neutral_percentage', 5, 2)->default(0.00);
            $table->decimal('negative_percentage', 5, 2)->default(0.00);

            // AI-generated insights
            $table->text('ai_summary_text')->nullable();
            $table->jsonb('top_positive_highlights')->default('[]');
            $table->jsonb('top_negative_highlights')->default('[]');
            $table->jsonb('most_frequent_keywords')->default('[]');

            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement(
                'CREATE UNIQUE INDEX idx_review_summaries_unique ON review_summaries (summarizable_type, COALESCE(summarizable_id, 0))'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_summaries');
        Schema::dropIfExists('reviews');
    }
};