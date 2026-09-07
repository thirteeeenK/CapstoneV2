<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityModel extends Model
{
    use HasFactory;

    protected $table = 'activities';

    protected $fillable = [
        'destination_id',
        'activity_name',
        'description',
        'category',
        'activity_level',
        'duration',
        'capacity',
        'requirements',
        'vibe_tags',
        'ideal_for',
        'rate',
        'inclusions',
        'exclusions',
        'itinerary',
        'notes',
        'images',
        'embedding',
        'is_shown',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'vibe_tags' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'itinerary' => 'array',
        'images' => 'array',
        'is_shown' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function destination()
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }

    /**
     * Resolve effective coordinates for this activity, falling back to its
     * destination center when the activity has no precise coordinates set.
     */
    public function getLatitudeWithFallbackAttribute(): ?float
    {
        if ($this->latitude !== null) {
            return (float) $this->latitude;
        }

        return $this->destination ? (float) $this->destination->latitude : null;
    }

    public function getLongitudeWithFallbackAttribute(): ?float
    {
        if ($this->longitude !== null) {
            return (float) $this->longitude;
        }

        return $this->destination ? (float) $this->destination->longitude : null;
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_activity', 'activity_id', 'package_id');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable', 'reviewable_type', 'reviewable_id');
    }

    public function reviewSummary()
    {
        return $this->morphOne(ReviewSummary::class, 'summarizable', 'summarizable_type', 'summarizable_id');
    }

    /**
     * Heuristic indoor/outdoor inference (no DB column — YAGNI until indoor inventory >20%).
     * Uses category → vibe_tags → description/inclusions ladder.
     */
    public function isIndoor(): bool
    {
        $cat = mb_strtolower($this->category ?? '');
        $vibes = mb_strtolower(implode(' ', (array) ($this->vibe_tags ?? [])));
        $text = mb_strtolower(($this->description ?? '').' '.($this->notes ?? '').' '.implode(' ', (array) ($this->inclusions ?? [])));
        $hay = "{$cat} {$vibes} {$text}";

        if (preg_match('/\b(indoor|museum|gallery|heritage|spa|wellness|massage|culinary|cooking|restaurant|bar|cafe|shopping|mall|cinema|theater|theatre)\b/', $hay)) {
            return true;
        }

        if (in_array($cat, ['museum', 'spa', 'wellness', 'restaurant', 'culinary', 'indoor', 'shopping'], true)) {
            return true;
        }

        // All current SunnyTrips categories are outdoor/water
        if (in_array($this->category, ['Island Hopping', 'Water Activity', 'Diving', 'Sailing', 'Adventure', 'Land Tour'], true)) {
            return false;
        }

        if (preg_match('/\b(boat|yacht|kayak|parasail|dive|snorkel|beach|island|atv|zipline|sail|surf|jet ski|banana boat|lagoon|cave|hike|trek)\b/', $hay)) {
            return false;
        }

        return false; // default outdoor for Boracay/El Nido beach inventory
    }

    public function environment(): string
    {
        return $this->isIndoor() ? 'indoor' : 'outdoor';
    }

    /**
     * Determine if the activity rate is priced per person/participant.
     */
    public function isPerPersonRate(): bool
    {
        $rateStr = mb_strtolower($this->rate ?? '');

        return str_contains($rateStr, '/person') || str_contains($rateStr, 'per person')
            || str_contains($rateStr, '/pax') || str_contains($rateStr, 'per pax')
            || str_contains($rateStr, '/head') || str_contains($rateStr, 'per head');
    }

    /**
     * Get maximum allowable capacity integer from capacity string.
     */
    public function getMaxCapacityInt(): int
    {
        $capStr = $this->capacity ?? '';
        if (preg_match_all('/\d+/', $capStr, $matches) && ! empty($matches[0])) {
            $numbers = array_map('intval', $matches[0]);

            return max($numbers);
        }

        return 20;
    }

    /**
     * Calculate rate per unit/pax given selected pax count.
     */
    public function calculateRateForPax(int $pax = 1): float
    {
        $rateStr = $this->rate ?? '0';
        $pax = max(1, $pax);

        // Check for range like ₱300–₱500 or 200-300
        if (preg_match('/(\d[\d,.]*)\s*[\-–—]\s*[^\d]*(\d[\d,.]*)/u', $rateStr, $m)) {
            $min = (float) str_replace(',', '', $m[1]);
            $max = (float) str_replace(',', '', $m[2]);

            return ($pax <= 1) ? $min : $max;
        }

        // Clean currency symbols and text
        $cleaned = preg_replace('/[^\d.]/', '', $rateStr);

        return (float) $cleaned;
    }
}
