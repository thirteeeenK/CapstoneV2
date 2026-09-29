<?php

namespace App\Services\Chat;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\GeminiService;
use Carbon\Carbon;

/**
 * Rebuilds the catalog evidence given to the chatbot from its returned card
 * identifiers. This is evaluation-only; no additional data is exposed in the
 * customer chat response.
 */
class ChatJudgeEvidenceContext
{
    public function __construct(private GeminiService $gemini) {}

    /** @param array<string, mixed> $reply */
    public function build(array $reply): string
    {
        $parts = array_filter([
            $this->hotelContext($reply),
            $this->roomContext($reply),
            $this->activityContext($reply),
            $this->packageContext($reply),
            $this->addOnContext($reply),
        ]);

        return $parts === []
            ? '(no records were retrieved for this query)'
            : implode("\n\n", $parts);
    }

    /** @param array<string, mixed> $reply */
    private function hotelContext(array $reply): string
    {
        return $this->gemini->getHotelContext(
            $this->entries($reply, 'retrieved_hotels', HotelModel::class, ['destination'])
        );
    }

    /** @param array<string, mixed> $reply */
    private function roomContext(array $reply): string
    {
        $cards = (array) ($reply['retrieved_rooms'] ?? []);

        return $this->gemini->getRoomContext(
            $this->entries($reply, 'retrieved_rooms', RoomType::class, ['hotel.destination']),
            null,
            $this->pax($cards),
            $this->nights($cards),
        );
    }

    /** @param array<string, mixed> $reply */
    private function activityContext(array $reply): string
    {
        $cards = (array) ($reply['retrieved_activities'] ?? []);

        return $this->gemini->getActivityContext(
            $this->entries($reply, 'retrieved_activities', ActivityModel::class, ['destination']),
            null,
            $this->pax($cards),
        );
    }

    /** @param array<string, mixed> $reply */
    private function packageContext(array $reply): string
    {
        $cards = (array) ($reply['retrieved_packages'] ?? []);

        return $this->gemini->getPackageContext(
            $this->entries($reply, 'retrieved_packages', Package::class, ['destination']),
            null,
            $this->pax($cards),
        );
    }

    /** @param array<string, mixed> $reply */
    private function addOnContext(array $reply): string
    {
        $cards = (array) ($reply['retrieved_addons'] ?? []);

        return $this->gemini->getAddOnContext(
            $this->entries($reply, 'retrieved_addons', AddOnModel::class, ['destination']),
            null,
            $this->pax($cards),
        );
    }

    /**
     * Preserve the response card order and score, so the judge receives the
     * same ranked records the response generator received.
     *
     * @param  array<string, mixed>  $reply
     * @param  class-string<HotelModel|RoomType|ActivityModel|Package|AddOnModel>  $model
     * @param  array<int, string>  $relations
     * @return array<int, array{item: HotelModel|RoomType|ActivityModel|Package|AddOnModel, score: float}>
     */
    private function entries(array $reply, string $key, string $model, array $relations): array
    {
        $cards = array_values(array_filter((array) ($reply[$key] ?? []), 'is_array'));
        $ids = array_values(array_unique(array_filter(array_map(
            fn (array $card): ?int => isset($card['id']) && is_numeric($card['id']) ? (int) $card['id'] : null,
            $cards,
        ))));

        if ($ids === []) {
            return [];
        }

        $items = $model::query()->with($relations)->whereKey($ids)->get()->keyBy('id');

        $entries = [];
        foreach ($cards as $card) {
            $item = $items->get((int) ($card['id'] ?? 0));
            if ($item === null) {
                continue;
            }
            $entries[] = [
                'item' => $item,
                'score' => (float) ($card['similarity_score'] ?? $card['similarity'] ?? 0.0),
            ];
        }

        return $entries;
    }

    /** @param array<int, mixed> $cards */
    private function pax(array $cards): ?int
    {
        foreach ($cards as $card) {
            if (is_array($card) && isset($card['pax']) && is_numeric($card['pax'])) {
                return (int) $card['pax'];
            }
        }

        return null;
    }

    /** @param array<int, mixed> $cards */
    private function nights(array $cards): ?int
    {
        foreach ($cards as $card) {
            if (! is_array($card) || empty($card['check_in']) || empty($card['check_out'])) {
                continue;
            }
            try {
                $nights = Carbon::parse((string) $card['check_in'])->diffInDays(Carbon::parse((string) $card['check_out']), false);

                return $nights > 0 ? $nights : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
