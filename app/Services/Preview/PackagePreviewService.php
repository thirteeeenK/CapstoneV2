<?php

namespace App\Services\Preview;

use App\Concerns\ResolvesImages;
use App\Models\Package;

class PackagePreviewService
{
    use ResolvesImages;

    /**
     * Build the JSON payload shared by the package preview modal (package
     * catalog, chat widget).
     */
    public function build(Package $package, ?array $priceChange = null): array
    {
        $imagesRaw = is_array($package->images) ? $package->images : (is_string($package->images) ? (json_decode($package->images, true) ?: []) : []);
        $resolvedImages = array_map(
            fn ($img) => self::resolveImg($img, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'),
            $imagesRaw
        );
        if (empty($resolvedImages)) {
            $resolvedImages = [self::resolveImg(null, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80')];
        }

        $inclusionsRaw = is_array($package->generic_inclusions)
            ? $package->generic_inclusions
            : (is_string($package->generic_inclusions) ? array_filter(array_map('trim', explode(',', $package->generic_inclusions))) : []);

        return [
            'id' => $package->id,
            'name' => $package->name,
            'type' => $package->type,
            'price' => '₱'.number_format((float) $package->price, 2),
            'days' => $package->days,
            'nights' => $package->nights,
            'min_pax' => $package->min_pax,
            'destination_name' => $package->destination->name ?? 'Philippines',
            'destination_id' => $package->destination_id,
            'images' => array_values($resolvedImages),
            'generic_inclusions' => array_values($inclusionsRaw),
            'hotels' => $package->hotels->map(fn ($h) => ['id' => $h->id, 'name' => $h->hotel_name])->values()->all(),
            'rooms' => $package->rooms->map(fn ($r) => ['id' => $r->id, 'name' => $r->room_name])->values()->all(),
            'activities' => $package->activities->map(fn ($a) => ['id' => $a->id, 'name' => $a->activity_name])->values()->all(),
            'add_ons' => $package->addOns->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all(),
            'valid_from' => $package->valid_from?->format('M d, Y'),
            'valid_to' => $package->valid_to?->format('M d, Y'),
            'price_change' => $priceChange,
        ];
    }
}
