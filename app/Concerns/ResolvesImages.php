<?php

namespace App\Concerns;

trait ResolvesImages
{
    public static function resolveImg(string $imgPath = null, string $fallback = null): string
    {
        if (empty($imgPath)) {
            return $fallback ?? 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80';
        }

        if (str_starts_with($imgPath, 'http://') || str_starts_with($imgPath, 'https://')) {
            return $imgPath;
        }

        return asset('storage/' . $imgPath);
    }

    public static function getCategoryIcon(string $category = null): string
    {
        $c = strtolower($category ?? '');

        $map = [
            'scuba_diving' => ['water', 'ocean', 'sea', 'snorkel', 'dive'],
            'hiking' => ['hik', 'trek', 'nature', 'eco'],
            'museum' => ['cultur', 'histor', 'heritage'],
            'restaurant' => ['food', 'culin', 'tour'],
            'paragliding' => ['adventure', 'extreme'],
            'spa' => ['relax', 'wellness', 'spa'],
            'beach_access' => ['island', 'beach'],
        ];

        foreach ($map as $icon => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($c, $keyword)) {
                    return $icon;
                }
            }
        }

        return 'explore';
    }

    public static function getAmenityIcon(string $amenity = null): string
    {
        $a = strtolower($amenity ?? '');

        $map = [
            'pool' => ['pool'],
            'wifi' => ['wifi', 'internet'],
            'local_bar' => ['bar', 'drink', 'mini bar'],
            'restaurant' => ['restaurant', 'food', 'dining', 'breakfast'],
            'spa' => ['spa', 'massage'],
            'beach_access' => ['beach', 'ocean', 'view'],
            'ac_unit' => ['air', 'climate', 'ac'],
            'fitness_center' => ['gym', 'fitness'],
            'deck' => ['balcony', 'terrace', 'patio'],
            'bathtub' => ['bath', 'shower', 'tub'],
            'concierge' => ['tour', 'desk', 'service'],
            'coffee_maker' => ['coffee', 'tea', 'espresso'],
            'tv' => ['tv', 'screen', 'television'],
            'king_bed' => ['bed', 'mattress'],
            'chair' => ['lounge', 'sofa', 'seating'],
            'lock' => ['safe', 'security'],
            'kitchen' => ['kitchen', 'fridge', 'refrigerator'],
        ];

        foreach ($map as $icon => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($a, $keyword)) {
                    return $icon;
                }
            }
        }

        return 'star';
    }
}
