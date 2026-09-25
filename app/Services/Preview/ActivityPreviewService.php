<?php

namespace App\Services\Preview;

use App\Concerns\ResolvesImages;
use App\Models\ActivityModel;

class ActivityPreviewService
{
    use ResolvesImages;

    /**
     * Build the JSON payload shared by the activity preview modal (activity
     * catalog, destination page, hotel page, chat widget).
     */
    public function build(ActivityModel $activity, ?array $priceChange = null): array
    {
        $imagesRaw = is_array($activity->images) ? $activity->images : (is_string($activity->images) ? (json_decode($activity->images, true) ?: []) : []);
        $actImg = self::resolveActivityImage($imagesRaw[0] ?? null, $activity->activity_name, $activity->category);

        $inclusionsRaw = is_array($activity->inclusions) ? $activity->inclusions : (is_string($activity->inclusions) ? array_filter(array_map('trim', explode(',', $activity->inclusions))) : []);
        $exclusionsRaw = is_array($activity->exclusions) ? $activity->exclusions : (is_string($activity->exclusions) ? array_filter(array_map('trim', explode(',', $activity->exclusions))) : []);
        $itineraryRaw = is_array($activity->itinerary) ? $activity->itinerary : (is_string($activity->itinerary) ? (json_decode($activity->itinerary, true) ?: []) : []);
        $vibeTagsRaw = is_array($activity->vibe_tags) ? $activity->vibe_tags : (is_string($activity->vibe_tags) ? array_filter(array_map('trim', explode(',', $activity->vibe_tags))) : []);

        $destName = $activity->destination->name ?? '';

        return [
            'id' => $activity->id,
            'activity_name' => $activity->activity_name,
            'category' => $activity->category,
            'category_icon' => self::getCategoryIcon($activity->category),
            'rate' => self::formatRate($activity->rate),
            'price_change' => $priceChange,
            'duration' => $activity->duration,
            'activity_level' => $activity->activity_level,
            'capacity' => $activity->capacity,
            'requirements' => $activity->requirements,
            'ideal_for' => $activity->ideal_for,
            'description' => $activity->description,
            'notes' => $activity->notes,
            'destination_name' => $destName,
            'destination_id' => $activity->destination_id,
            'images' => [$actImg],
            'inclusions' => array_values($inclusionsRaw),
            'exclusions' => array_values($exclusionsRaw),
            'itinerary' => array_values($itineraryRaw),
            'vibe_tags' => array_values($vibeTagsRaw),
        ];
    }
}
