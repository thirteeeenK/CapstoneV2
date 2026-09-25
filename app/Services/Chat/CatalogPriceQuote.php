<?php

namespace App\Services\Chat;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\Package;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Model;

final class CatalogPriceQuote
{
    public function for(Model $item, ?int $pax = null, ?int $nights = null): array
    {
        $pax = max(1, $pax ?? 1);

        return match (true) {
            $item instanceof RoomType => $this->room($item, $pax, $nights),
            $item instanceof ActivityModel => $this->activity($item, $pax),
            $item instanceof Package => $this->package($item, $pax),
            $item instanceof AddOnModel => $this->addon($item, $pax),
            default => $this->unknown($pax, $nights),
        };
    }

    private function room(RoomType $room, int $pax, ?int $nights): array
    {
        $unit = round($room->calculateNightlyRate($pax), 2);
        $nights = $nights !== null ? max(1, $nights) : null;
        $total = $nights !== null ? round($unit * $nights, 2) : $unit;
        $display = '₱'.number_format($unit, 2)." per night for {$pax} pax";
        if ($nights !== null) {
            $display .= ' — ₱'.number_format($total, 2)." for {$nights} night".($nights === 1 ? '' : 's');
        }

        return $this->result('per_night', $unit, null, $pax, $nights, $total, null, $display);
    }

    private function activity(ActivityModel $activity, int $pax): array
    {
        [$minimum, $maximum] = $this->amountRange((string) $activity->rate);
        if ($minimum === null) {
            return $this->unknown($pax, null, (string) $activity->rate);
        }
        $basis = $activity->pricingBasis();
        $multiplier = $basis === 'per_person' ? $pax : 1;
        $total = round($minimum * $multiplier, 2);
        $totalMax = $maximum !== null ? round($maximum * $multiplier, 2) : null;
        $unit = $maximum === null ? '₱'.number_format($minimum, 2) : '₱'.number_format($minimum, 2).'–₱'.number_format($maximum, 2);
        $suffix = match ($basis) {
            'per_person' => ' per person', 'per_hour' => ' per hour', 'per_group' => ' per group', 'per_unit' => ' per unit', default => ''
        };
        $display = $unit.$suffix;
        if ($basis === 'per_person') {
            $totalDisplay = $totalMax === null ? '₱'.number_format($total, 2) : '₱'.number_format($total, 2).'–₱'.number_format($totalMax, 2);
            $display .= " — {$totalDisplay} total for {$pax} pax";
        }

        return $this->result($basis, $minimum, $maximum, $pax, null, $total, $totalMax, $display);
    }

    private function package(Package $package, int $pax): array
    {
        $unit = is_numeric($package->price) ? round((float) $package->price, 2) : null;
        if ($unit === null) {
            return $this->unknown($pax, null);
        }
        $total = round($unit * $pax, 2);

        return $this->result('package_per_pax', $unit, null, $pax, null, $total, null, '₱'.number_format($unit, 2).' per pax — ₱'.number_format($total, 2)." total for {$pax} pax");
    }

    private function addon(AddOnModel $addon, int $pax): array
    {
        $tier = $addon->pricingTierForPax($pax);
        if ($tier === null) {
            return $this->unknown($pax, null);
        }
        if (isset($tier['total_rate'])) {
            $total = $this->numericAmount($tier['total_rate']);
            if ($total === null) {
                return $this->unknown($pax, null);
            }

            return $this->result('tiered_total', $total, null, $pax, null, $total, null, '₱'.number_format($total, 2)." total for {$pax} pax");
        }
        $unit = $this->numericAmount($tier['rate_per_pax'] ?? $tier['rate'] ?? null);
        if ($unit === null) {
            return $this->unknown($pax, null);
        }
        $total = round($unit * $pax, 2);

        return $this->result('tiered_per_person', $unit, null, $pax, null, $total, null, '₱'.number_format($unit, 2).' per person — ₱'.number_format($total, 2)." total for {$pax} pax");
    }

    private function amountRange(string $value): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $value, $matches);
        $amounts = array_values(array_filter(array_map(fn (string $amount): ?float => $this->numericAmount($amount), $matches[0] ?? []), fn (?float $amount): bool => $amount !== null));

        return [$amounts[0] ?? null, $amounts[1] ?? null];
    }

    private function numericAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value) || ! preg_match('/\d[\d,]*(?:\.\d+)?/', $value, $match)) {
            return null;
        }

        return (float) str_replace(',', '', $match[0]);
    }

    private function result(string $basis, ?float $unit, ?float $unitMax, int $pax, ?int $nights, ?float $total, ?float $totalMax, string $display): array
    {
        return ['basis' => $basis, 'unit_amount' => $unit, 'unit_max' => $unitMax, 'pax' => $pax, 'nights' => $nights, 'total' => $total, 'total_max' => $totalMax, 'display' => $display, 'calculable' => true];
    }

    private function unknown(int $pax, ?int $nights, string $stored = ''): array
    {
        return ['basis' => 'unknown', 'unit_amount' => null, 'unit_max' => null, 'pax' => $pax, 'nights' => $nights, 'total' => null, 'total_max' => null, 'display' => trim($stored) !== '' ? trim($stored).' (total unavailable)' : 'Price unavailable', 'calculable' => false];
    }
}
