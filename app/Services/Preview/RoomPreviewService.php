<?php

namespace App\Services\Preview;

use App\Concerns\ResolvesImages;
use App\Models\RoomType;

class RoomPreviewService
{
    use ResolvesImages;

    /**
     * Build the JSON payload shared by the room preview modal (hotel page,
     * chat widget, room catalog). Mirrors the hotel/show inline payload.
     */
    public function build(RoomType $room, ?string $fallbackImage = null): array
    {
        $roomImagesRaw = is_array($room->images) ? $room->images : (is_string($room->images) ? (json_decode($room->images, true) ?: []) : []);
        $resolvedRoomImages = array_map(
            fn ($img) => self::resolveImg($img, $fallbackImage),
            $roomImagesRaw
        );
        if (empty($resolvedRoomImages)) {
            $resolvedRoomImages = [self::resolveImg(null, $fallbackImage)];
        }

        $roomAmenitiesRaw = is_array($room->room_amenities)
            ? $room->room_amenities
            : (is_string($room->room_amenities) ? array_filter(array_map('trim', explode(',', $room->room_amenities))) : []);

        $roomPublishedReviews = $room->reviews
            ->where('is_published', true)
            ->sortByDesc('created_at')
            ->take(3)
            ->values();

        $roomReviewPayload = $roomPublishedReviews->map(fn ($rv) => [
            'reviewer_alias' => $rv->reviewer_alias,
            'rating' => (int) $rv->rating,
            'sentiment' => $rv->sentiment,
            'comment' => $rv->comment,
            'keywords' => $rv->extracted_keywords ?? [],
            'created_at_label' => $rv->created_at?->format('M j, Y'),
        ]);

        $roomSummary = $room->reviewSummary;

        return [
            'id' => $room->id,
            'room_name' => $room->room_name,
            'base_price' => (float) $room->base_price,
            'occupancy' => $room->occupancy,
            'base_occupancy' => $room->base_occupancy,
            'max_occupancy' => $room->max_occupancy,
            'extra_person_fee' => (float) ($room->extra_person_fee ?: 0),
            'bed_configuration' => $room->bed_configuration,
            'room_size' => $room->room_size,
            'view_type' => $room->view_type,
            'description' => $room->description,
            'ideal_guest' => $room->ideal_guest ?? $room->ideal_for ?? null,
            'total_rooms' => $room->total_rooms ?? null,
            'is_shown' => (bool) $room->is_shown,
            'images' => $resolvedRoomImages,
            'amenities' => array_values($roomAmenitiesRaw),
            'review_summary' => $roomSummary && $roomSummary->total_reviews > 0 ? [
                'average_rating' => (float) $roomSummary->average_rating,
                'total_reviews' => (int) $roomSummary->total_reviews,
                'positive_percentage' => (float) $roomSummary->positive_percentage,
                'neutral_percentage' => (float) $roomSummary->neutral_percentage,
                'negative_percentage' => (float) $roomSummary->negative_percentage,
                'ai_summary_text' => $roomSummary->ai_summary_text,
            ] : null,
            'reviews' => $roomReviewPayload,
        ];
    }
}
