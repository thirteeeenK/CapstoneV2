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

    public static function resolveActivityImage(?string $imgPath, ?string $activityName = null, ?string $category = null): string
    {
        if (!empty($imgPath)) {
            return static::resolveImg($imgPath);
        }

        $haystack = strtolower(($activityName ?? '') . ' ' . ($category ?? ''));

        if (str_contains($haystack, 'parasail')) {
            return 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'kayak')) {
            return 'https://images.unsplash.com/photo-1508873696983-2df515122519?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'zipline') || str_contains($haystack, 'canopy') || str_contains($haystack, 'taraw')) {
            return 'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'scuba') || str_contains($haystack, 'helmet') || str_contains($haystack, 'dive')) {
            return 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'jet ski')) {
            return 'https://images.unsplash.com/photo-1563299796-b729d0af54a5?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'atv') || str_contains($haystack, 'buggy')) {
            return 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'paraw') || str_contains($haystack, 'sunset') || str_contains($haystack, 'sail')) {
            return 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'trike') || str_contains($haystack, 'land tour') || str_contains($haystack, 'van') || str_contains($haystack, 'multicab')) {
            return 'https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'island hopping') || str_contains($haystack, 'boat')) {
            return 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'party') || str_contains($haystack, 'yacht')) {
            return 'https://images.unsplash.com/photo-1567899378494-47b22a2ae96a?auto=format&fit=crop&w=800&q=80';
        }

        return 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80';
    }

    public static function formatRate($rate): string
    {
        $rateText = (string) $rate;
        if (is_numeric($rateText)) {
            return '₱' . number_format((float) $rateText, 2);
        }
        if (str_starts_with($rateText, '₱')) {
            return $rateText;
        }
        return '₱' . $rateText;
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

