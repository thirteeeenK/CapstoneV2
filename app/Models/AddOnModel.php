<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddOnModel extends Model
{
    use HasFactory;

    protected $table = 'add_ons';

    protected $fillable = [
        'destination_id',
        'name',
        'type',
        'description',
        'inclusions',
        'pricing_tiers',
        'surcharges',
        'is_shown',
        'embedding',
    ];

    protected $casts = [
        'inclusions' => 'array',
        'pricing_tiers' => 'array',
        'surcharges' => 'array',
        'is_shown' => 'boolean',
    ];

    public function destination()
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_addon', 'add_on_id', 'package_id')
            ->withTimestamps();
    }

    /**
     * Get rate per pax from matching pricing tier.
     */
    public function getRateForPax(int $pax = 1): float
    {
        $pax = max(1, $pax);
        $tiers = $this->pricing_tiers;

        if (is_array($tiers) && count($tiers) > 0) {
            $matchingTier = null;
            foreach ($tiers as $tier) {
                $minPax = (int) ($tier['min_pax'] ?? 1);
                $maxPax = (int) ($tier['max_pax'] ?? 999);
                if ($pax >= $minPax && $pax <= $maxPax) {
                    $matchingTier = $tier;
                    break;
                }
            }

            if (! $matchingTier) {
                // If pax exceeds highest tier min_pax, use last tier
                $matchingTier = end($tiers);
            }

            if ($matchingTier) {
                if (isset($matchingTier['rate'])) {
                    return (float) $matchingTier['rate'];
                }
                if (isset($matchingTier['rate_per_pax'])) {
                    return (float) $matchingTier['rate_per_pax'];
                }
                if (isset($matchingTier['total_rate'])) {
                    return (float) $matchingTier['total_rate'] / max(1, $pax);
                }
            }
        }

        return 0.00;
    }

    /** @return array<string, mixed>|null */
    public function pricingTierForPax(int $pax = 1): ?array
    {
        $pax = max(1, $pax);
        $tiers = array_values(array_filter((array) $this->pricing_tiers, 'is_array'));
        foreach ($tiers as $tier) {
            if ($pax >= (int) ($tier['min_pax'] ?? 1) && $pax <= (int) ($tier['max_pax'] ?? PHP_INT_MAX)) {
                return $tier;
            }
        }

        return $tiers === [] ? null : $tiers[array_key_last($tiers)];
    }

    /**
     * Get maximum passenger capacity configured in pricing tiers.
     */
    public function getMaxPax(): int
    {
        $tiers = $this->pricing_tiers;
        if (is_array($tiers) && count($tiers) > 0) {
            $maxPaxList = array_map(fn ($t) => (int) ($t['max_pax'] ?? 999), $tiers);
            $valid = array_filter($maxPaxList, fn ($val) => $val < 999);

            return ! empty($valid) ? max($valid) : 20;
        }

        return 20;
    }
}
