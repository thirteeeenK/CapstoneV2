<?php

namespace App\Models;

use App\Concerns\ResolvesImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $check_in_date
 * @property Carbon|null $check_out_date
 */
class CartItem extends Model
{
    use HasFactory;

    protected $table = 'cart_items';

    protected $fillable = [
        'user_id',
        'session_token',
        'item_type',
        'item_id',
        'quantity',
        'check_in_date',
        'check_out_date',
        'selected_pax',
        'room_option',
        'is_selected',
        'notes',
        'lucky_group_id',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'is_selected' => 'boolean',
        'quantity' => 'integer',
        'selected_pax' => 'integer',
    ];

    protected $appends = [
        'unit_rate',
        'subtotal',
        'item_title',
        'item_subtitle',
        'item_image',
        'hotel_name',
        'location_name',
        'date_details',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function itemable()
    {
        return $this->morphTo('itemable', 'item_type', 'item_id');
    }

    /**
     * Get hotel name if applicable.
     */
    public function getHotelNameAttribute()
    {
        $item = $this->itemable;
        if (! $item) {
            return null;
        }

        if ($this->item_type === 'room') {
            return $item->hotel ? ($item->hotel->hotel_name ?? $item->hotel->name ?? null) : null;
        }

        if ($this->item_type === 'package' && method_exists($item, 'hotels') && $item->relationLoaded('hotels')) {
            $names = $item->hotels->map(fn ($h) => $h->hotel_name ?? $h->name)->filter()->toArray();

            return ! empty($names) ? implode(', ', $names) : null;
        }

        return null;
    }

    /**
     * Get destination location name if applicable.
     */
    public function getLocationNameAttribute()
    {
        $item = $this->itemable;
        if (! $item) {
            return null;
        }

        if ($this->item_type === 'room' && $item->hotel) {
            return $item->hotel->destination ? ($item->hotel->destination->destination_name ?? $item->hotel->destination->name ?? null) : null;
        }

        if (isset($item->destination) && $item->destination) {
            return $item->destination->destination_name ?? $item->destination->name ?? null;
        }

        return null;
    }

    /**
     * Get formatted stay date range.
     */
    public function getDateDetailsAttribute()
    {
        if ($this->check_in_date && $this->check_out_date) {
            $checkIn = Carbon::parse($this->check_in_date);
            $checkOut = Carbon::parse($this->check_out_date);
            $nights = max(1, $checkIn->diffInDays($checkOut));

            return $checkIn->format('M d').' - '.$checkOut->format('M d, Y')." ({$nights} night".($nights > 1 ? 's' : '').')';
        }

        return null;
    }

    /**
     * Strip currency symbols and formatting to get a numeric value.
     */
    protected function parseCurrency($value, int $pax = 1): float
    {
        if (is_null($value)) {
            return 0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $valStr = (string) $value;

        // Check for range strings e.g. "₱300–₱500/person" or "200-300"
        if (preg_match('/(\d[\d,.]*)\s*[\-–—]\s*[^\d]*(\d[\d,.]*)/u', $valStr, $m)) {
            $min = (float) str_replace(',', '', $m[1]);
            $max = (float) str_replace(',', '', $m[2]);

            return ($pax <= 1) ? $min : $max;
        }

        // Strip ₱, PHP, commas, spaces, and other non-numeric chars except dots
        $cleaned = preg_replace('/[^\d.]/', '', $valStr);

        return (float) $cleaned;
    }

    /**
     * Calculate unit rate based on item type and pax pricing.
     */
    public function getUnitRateAttribute()
    {
        $item = $this->itemable;
        if (! $item) {
            return 0;
        }

        $pax = max(1, (int) ($this->selected_pax ?: 1));
        if (in_array($this->item_type, ['activity', 'addon'])) {
            $pax = max(1, (int) ($this->selected_pax ?: 1), (int) ($this->quantity ?: 1));
        } elseif ($this->item_type === 'package') {
            // min_pax only gates booking eligibility at checkout, never inflates pax
            $pax = max(1, (int) ($this->selected_pax ?: 1), (int) ($this->quantity ?: 1));
        }

        switch ($this->item_type) {
            case 'room':
                if (method_exists($item, 'calculateNightlyRate')) {
                    return $item->calculateNightlyRate(max(1, (int) ($this->selected_pax ?: 2)));
                }

                return $this->parseCurrency($item->base_price ?? $item->rate_per_night ?? 0, $pax);

            case 'activity':
                if (method_exists($item, 'calculateRateForPax')) {
                    return $item->calculateRateForPax($pax);
                }

                return $this->parseCurrency($item->rate ?? 0, $pax);

            case 'package':
                return $this->parseCurrency($item->price ?? 0, $pax);

            case 'addon':
                if (method_exists($item, 'getRateForPax')) {
                    $tierRate = $item->getRateForPax($pax);
                    if ($tierRate > 0) {
                        return $tierRate;
                    }
                }

                // Check if add-on has pax pricing tiers
                if (! empty($item->pricing_tiers) && is_array($item->pricing_tiers)) {
                    // Match pricing tier or find closest tier
                    $matchingTier = null;
                    foreach ($item->pricing_tiers as $tier) {
                        $minPax = (int) ($tier['min_pax'] ?? 1);
                        $maxPax = (int) ($tier['max_pax'] ?? 999);
                        if ($pax >= $minPax && $pax <= $maxPax) {
                            $matchingTier = $tier;
                            break;
                        }
                    }
                    if (! $matchingTier && ! empty($item->pricing_tiers)) {
                        $matchingTier = end($item->pricing_tiers);
                    }

                    if ($matchingTier) {
                        if (isset($matchingTier['rate'])) {
                            return $this->parseCurrency($matchingTier['rate'], $pax);
                        }
                        if (isset($matchingTier['rate_per_pax'])) {
                            return $this->parseCurrency($matchingTier['rate_per_pax'], $pax);
                        }
                        if (isset($matchingTier['total_rate'])) {
                            return $this->parseCurrency($matchingTier['total_rate'], $pax) / $pax;
                        }
                    }
                }

                return $this->parseCurrency($item->base_price ?? $item->rate ?? 0, $pax);

            default:
                return $this->parseCurrency($item->price ?? $item->base_price ?? $item->rate ?? 0, $pax);
        }
    }

    /**
     * Calculate item subtotal.
     */
    public function getSubtotalAttribute()
    {
        $unitRate = $this->unit_rate;

        if ($this->item_type === 'room' && $this->check_in_date && $this->check_out_date) {
            $checkIn = Carbon::parse($this->check_in_date);
            $checkOut = Carbon::parse($this->check_out_date);
            $nights = max(1, $checkIn->diffInDays($checkOut));

            return $unitRate * $nights * max(1, $this->quantity);
        }

        if ($this->item_type === 'addon') {
            // Transfers/Add-ons: effective pax * unit rate
            $effectivePax = max(1, (int) ($this->selected_pax ?: 1), (int) ($this->quantity ?: 1));

            return $unitRate * $effectivePax;
        }

        if ($this->item_type === 'activity') {
            $item = $this->itemable;
            $effectivePax = max(1, (int) ($this->selected_pax ?: 1), (int) ($this->quantity ?: 1));

            if ($item && method_exists($item, 'isPerPersonRate') && ! $item->isPerPersonRate()) {
                // Flat group rate (e.g. ₱2,000 for E-Trike 1-6 persons)
                return $unitRate * max(1, $this->quantity);
            }

            // Default activity tickets: unit rate per pax * effective pax
            return $unitRate * $effectivePax;
        }

        if ($this->item_type === 'package') {
            // min_pax only gates booking eligibility at checkout, never inflates pax
            $effectivePax = max(1, (int) ($this->selected_pax ?: 1), (int) ($this->quantity ?: 1));

            $subtotal = $unitRate * $effectivePax;

            return $subtotal;
        }

        return $unitRate * max(1, $this->quantity);
    }

    /**
     * Get item title.
     */
    public function getItemTitleAttribute()
    {
        $item = $this->itemable;
        if (! $item) {
            return 'Unavailable Item';
        }

        if ($this->item_type === 'room') {
            $hotel = $this->hotel_name;

            return $hotel ? "{$hotel} — {$item->room_name}" : $item->room_name;
        }

        return $item->activity_name ?? $item->name ?? $item->room_name ?? 'Travel Item';
    }

    /**
     * Get item subtitle / metadata info.
     */
    public function getItemSubtitleAttribute()
    {
        $item = $this->itemable;
        if (! $item) {
            return '';
        }

        if ($this->item_type === 'room') {
            $parts = [];
            if ($this->hotel_name) {
                $parts[] = $this->hotel_name;
            }
            if (! empty($item->occupancy)) {
                $parts[] = "Max {$item->occupancy} Guests";
            }
            if (! empty($item->bed_configuration)) {
                $parts[] = $item->bed_configuration;
            }
            if ($this->date_details) {
                $parts[] = $this->date_details;
            }

            return ! empty($parts) ? implode(' • ', $parts) : 'Room Stay';
        }

        if ($this->item_type === 'activity') {
            $parts = [];
            if ($this->location_name) {
                $parts[] = $this->location_name;
            }
            if (! empty($item->duration)) {
                $parts[] = $item->duration;
            }
            $effPax = max(1, (int) ($this->selected_pax ?: 1), (int) ($this->quantity ?: 1));
            $parts[] = $effPax.' pax';

            return implode(' • ', $parts);
        }

        if ($this->item_type === 'package') {
            $parts = [];
            if ($this->location_name) {
                $parts[] = $this->location_name;
            }
            $duration = ($item->days ? $item->days.'D' : '').($item->nights ? $item->nights.'N' : '');
            if ($duration) {
                $parts[] = $duration.' Package';
            }
            // Room preference only — never affects price (per-pax pricing covers occupancy).
            if ($this->room_option === 'separate') {
                $parts[] = 'Separate rooms';
            } elseif ($this->room_option === 'shared') {
                $parts[] = 'Share 1 room';
            }

            return implode(' • ', $parts);
        }

        if ($this->item_type === 'addon') {
            $parts = [];
            if ($this->location_name) {
                $parts[] = $this->location_name;
            }
            $parts[] = 'Transfer & Add-on';
            $effPax = max(1, (int) ($this->selected_pax ?: 1), (int) ($this->quantity ?: 1));
            $parts[] = $effPax.' pax';

            return implode(' • ', $parts);
        }

        return '';
    }

    /**
     * Whether this cart item's check-in date is in the past (Option A: check_in < today).
     * Only room items with dates can expire; other types return false.
     */
    public function isExpired(): bool
    {
        if ($this->item_type !== 'room' || ! $this->check_in_date) {
            return false;
        }

        return Carbon::parse($this->check_in_date)->lt(Carbon::today());
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    /**
     * Get primary image URL.
     */
    public function getItemImageAttribute()
    {
        $item = $this->itemable;
        if (! $item) {
            return asset('images/placeholder.jpg');
        }

        $images = $item->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (is_array($images) && count($images) > 0) {
            $img = $images[0];
            if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
                return $img;
            }

            return ResolvesImages::resolveImg($img, asset('images/placeholder.jpg'));
        }

        return asset('images/placeholder.jpg');
    }
}
