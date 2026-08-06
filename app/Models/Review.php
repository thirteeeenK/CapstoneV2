<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Review extends Model
{
    protected $table = 'reviews';

    public const SENTIMENT_POSITIVE = 'positive';
    public const SENTIMENT_NEUTRAL = 'neutral';
    public const SENTIMENT_NEGATIVE = 'negative';

    protected $fillable = [
        'booking_id',
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'hotel_id',
        'room_id',
        'activity_id',
        'package_id',
        'rating',
        'comment',
        'sentiment',
        'sentiment_score',
        'extracted_keywords',
        'reviewer_name',
        'is_verified_booking',
        'is_published',
        'is_featured',
    ];

    protected $casts = [
        'rating' => 'integer',
        'sentiment_score' => 'decimal:4',
        'extracted_keywords' => 'array',
        'is_verified_booking' => 'boolean',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function reviewable()
    {
        return $this->morphTo('reviewable', 'reviewable_type', 'reviewable_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hotel()
    {
        return $this->belongsTo(HotelModel::class, 'hotel_id');
    }

    public function room()
    {
        return $this->belongsTo(RoomType::class, 'room_id');
    }

    public function activity()
    {
        return $this->belongsTo(ActivityModel::class, 'activity_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    /**
     * First name + last initial for guest privacy (e.g. "John D.").
     * Manual admin reviews return the provided reviewer_name as-is.
     */
    public function getReviewerAliasAttribute(): string
    {
        if ($this->reviewer_name) {
            return $this->reviewer_name;
        }

        $name = $this->user?->name ?? '';

        $parts = preg_split('/\s+/', trim($name));
        $first = $parts[0] ?? null;

        if (!$first) {
            return 'Guest';
        }

        if (count($parts) > 1) {
            $lastInitial = mb_substr($parts[count($parts) - 1], 0, 1);
            return "{$first} {$lastInitial}.";
        }

        return $first . ' D.';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOfEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reviewable_type', $type)
            ->where('reviewable_id', $id);
    }
}