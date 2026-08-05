<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'is_selected',
        'notes',
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
        if (!$item) return null;

        if ($this->item_type === 'room') {
            return $item->hotel ? ($item->hotel->hotel_name ?? $item->hotel->name ?? null) : null;
        }

        if ($this->item_type === 'package' && method_exists($item, 'hotels') && $item->relationLoaded('hotels')) {
            $names = $item->hotels->map(fn($h) => $h->hotel_name ?? $h->name)->filter()->toArray();
            return !empty($names) ? implode(', ', $names) : null;
        }

        return null;
    }

    /**
     * Get destination location name if applicable.
     */
    public function getLocationNameAttribute()
    {
        $item = $this->itemable;
        if (!$item) return null;

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
            $nights = max(1, $this->check_in_date->diffInDays($this->check_out_date));
            return $this->check_in_date->format('M d') . ' - ' . $this->check_out_date->format('M d, Y') . " ({$nights} night" . ($nights > 1 ? 's' : '') . ')';
        }
        return null;
    }

    /**
     * Strip currency symbols and formatting to get a numeric value.
     */
    protected function parseCurrency($value): float
    {
        if (is_null($value)) return 0;
        if (is_numeric($value)) return (float) $value;
        // Strip ₱, PHP, commas, spaces, and other non-numeric chars except dots
        $cleaned = preg_replace('/[^\d.]/', '', (string) $value);
        return (float) $cleaned;
    }

    /**
     * Calculate unit rate based on item type and pax pricing.
     */
    public function getUnitRateAttribute()
    {
        $item = $this->itemable;
        if (!$item) {
            return 0;
        }

        switch ($this->item_type) {
            case 'room':
                if (method_exists($item, 'calculateNightlyRate')) {
                    return $item->calculateNightlyRate(max(1, (int)($this->selected_pax ?: 2)));
                }
                return $this->parseCurrency($item->base_price ?? $item->rate_per_night ?? 0);

            case 'activity':
                return $this->parseCurrency($item->rate ?? 0);

            case 'package':
                return $this->parseCurrency($item->price ?? 0);

            case 'addon':
                // Check if add-on has pax pricing tiers
                if (!empty($item->pricing_tiers) && is_array($item->pricing_tiers)) {
                    $pax = max(1, (int) $this->selected_pax);
                    // Match pricing tier or find closest tier
                    $matchingTier = null;
                    foreach ($item->pricing_tiers as $tier) {
                        $minPax = $tier['min_pax'] ?? 1;
                        $maxPax = $tier['max_pax'] ?? 999;
                        if ($pax >= $minPax && $pax <= $maxPax) {
                            $matchingTier = $tier;
                            break;
                        }
                    }
                    if ($matchingTier && isset($matchingTier['rate_per_pax'])) {
                        return $this->parseCurrency($matchingTier['rate_per_pax']);
                    } elseif ($matchingTier && isset($matchingTier['total_rate'])) {
                        return $this->parseCurrency($matchingTier['total_rate']) / max(1, $pax);
                    }
                }
                return $this->parseCurrency($item->base_price ?? $item->rate ?? 0);

            default:
                return $this->parseCurrency($item->price ?? $item->base_price ?? $item->rate ?? 0);
        }
    }

    /**
     * Calculate item subtotal.
     */
    public function getSubtotalAttribute()
    {
        $unitRate = $this->unit_rate;

        if ($this->item_type === 'room' && $this->check_in_date && $this->check_out_date) {
            $nights = max(1, $this->check_in_date->diffInDays($this->check_out_date));
            return $unitRate * $nights * max(1, $this->quantity);
        }

        if ($this->item_type === 'addon') {
            // Transfers/Add-ons: pax count * unit rate * quantity
            return $unitRate * max(1, $this->selected_pax) * max(1, $this->quantity);
        }

        if ($this->item_type === 'activity') {
            // Activity tickets: pax count * rate or quantity * rate
            $pax = max(1, $this->selected_pax ?: $this->quantity);
            return $unitRate * $pax;
        }

        return $unitRate * max(1, $this->quantity);
    }

    /**
     * Get item title.
     */
    public function getItemTitleAttribute()
    {
        $item = $this->itemable;
        if (!$item) return 'Unavailable Item';

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
        if (!$item) return '';

        if ($this->item_type === 'room') {
            $parts = [];
            if ($this->hotel_name) {
                $parts[] = $this->hotel_name;
            }
            if (!empty($item->occupancy)) {
                $parts[] = "Max {$item->occupancy} Guests";
            }
            if (!empty($item->bed_configuration)) {
                $parts[] = $item->bed_configuration;
            }
            if ($this->date_details) {
                $parts[] = $this->date_details;
            }
            return !empty($parts) ? implode(' • ', $parts) : 'Room Stay';
        }

        if ($this->item_type === 'activity') {
            $parts = [];
            if ($this->location_name) $parts[] = $this->location_name;
            if (!empty($item->duration)) $parts[] = $item->duration;
            if ($this->selected_pax) $parts[] = $this->selected_pax . ' pax';
            return implode(' • ', $parts);
        }

        if ($this->item_type === 'package') {
            $parts = [];
            if ($this->location_name) $parts[] = $this->location_name;
            $duration = ($item->days ? $item->days . 'D' : '') . ($item->nights ? $item->nights . 'N' : '');
            if ($duration) $parts[] = $duration . ' Package';
            return implode(' • ', $parts);
        }

        if ($this->item_type === 'addon') {
            $parts = [];
            if ($this->location_name) $parts[] = $this->location_name;
            $parts[] = 'Transfer & Add-on';
            if ($this->selected_pax) $parts[] = $this->selected_pax . ' pax';
            return implode(' • ', $parts);
        }

        return '';
    }

    /**
     * Get primary image URL.
     */
    public function getItemImageAttribute()
    {
        $item = $this->itemable;
        if (!$item) return asset('images/placeholder.jpg');

        $images = $item->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (is_array($images) && count($images) > 0) {
            $img = $images[0];
            if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
                return $img;
            }
            return asset('storage/' . $img);
        }

        return asset('images/placeholder.jpg');
    }
}
