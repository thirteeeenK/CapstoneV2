<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewSummary extends Model
{
    protected $table = 'review_summaries';

    public const PLATFORM_OVERALL_TYPE = 'SunnyTripsOverall';

    protected $fillable = [
        'summarizable_type',
        'summarizable_id',
        'total_reviews',
        'average_rating',
        'positive_percentage',
        'neutral_percentage',
        'negative_percentage',
        'ai_summary_text',
        'top_positive_highlights',
        'top_negative_highlights',
        'most_frequent_keywords',
        'last_analyzed_at',
    ];

    protected $casts = [
        'total_reviews' => 'integer',
        'average_rating' => 'decimal:2',
        'positive_percentage' => 'decimal:2',
        'neutral_percentage' => 'decimal:2',
        'negative_percentage' => 'decimal:2',
        'top_positive_highlights' => 'array',
        'top_negative_highlights' => 'array',
        'most_frequent_keywords' => 'array',
        'last_analyzed_at' => 'datetime',
    ];

    public function summarizable()
    {
        return $this->morphTo('summarizable', 'summarizable_type', 'summarizable_id');
    }

    /**
     * Whether this is the platform-wide overall summary record.
     */
    public function isPlatformOverall(): bool
    {
        return $this->summarizable_type === self::PLATFORM_OVERALL_TYPE;
    }
}