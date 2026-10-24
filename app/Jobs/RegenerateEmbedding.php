<?php

namespace App\Jobs;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\Recommendations\RecommendationExplainer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Retries an embedding Gemini could not produce while a listing or onboarding
 * profile was saved. The text is rebuilt from the record's current data on
 * every attempt, so a retry never stores a vector for an outdated version.
 */
class RegenerateEmbedding implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Seconds before each retry while Gemini keeps returning no vector. */
    private const RETRY_DELAYS = [60, 300, 900, 3600];

    /**
     * @param  'hotel'|'room'|'activity'|'addon'|'package'|'user'  $type
     */
    public function __construct(public string $type, public int $id) {}

    public function handle(GeminiService $gemini, RecommendationExplainer $explainer): void
    {
        $record = $this->record();
        if (! $record) {
            return;
        }
        [$text, $taskType, $title] = $this->source($gemini, $record);
        $vector = $text === null ? null : $gemini->generateEmbedding($text, $taskType, $title);
        if (! $vector) {
            if ($text !== null) {
                $this->release(self::RETRY_DELAYS[min($this->attempts(), count(self::RETRY_DELAYS)) - 1]);
            }

            return;
        }

        $record->forceFill([$this->type === 'user' ? 'preferences_embedding' : 'embedding' => $gemini->formatVectorForDb($vector)])->save();
        if ($record instanceof User && $record->userPreference) {
            $explainer->invalidateForUser($record->userPreference);
        }
    }

    private function record(): ?Model
    {
        return match ($this->type) {
            'hotel' => HotelModel::with('destination')->find($this->id),
            'room' => RoomType::with('hotel.destination')->find($this->id),
            'activity' => ActivityModel::with('destination')->find($this->id),
            'addon' => AddOnModel::find($this->id),
            'package' => Package::with(['destination', 'hotels', 'rooms', 'activities', 'addOns'])->find($this->id),
            'user' => User::with('userPreference')->find($this->id),
        };
    }

    /**
     * The same text, task type and title the saving controller embeds.
     *
     * @return array{0: ?string, 1: string, 2: ?string}
     */
    private function source(GeminiService $gemini, Model $record): array
    {
        return match ($this->type) {
            'hotel' => [$gemini->buildHotelEmbeddingText($record, $record->destination?->name), 'RETRIEVAL_DOCUMENT', $record->hotel_name],
            'room' => [$gemini->buildRoomEmbeddingText($record, $record->hotel?->hotel_name, $record->hotel?->destination?->name), 'RETRIEVAL_DOCUMENT', $record->room_name],
            'activity' => [$gemini->buildActivityEmbeddingText($record, $record->destination?->name), 'RETRIEVAL_DOCUMENT', $record->activity_name],
            'addon' => [$gemini->buildAddOnEmbeddingText($record), 'RETRIEVAL_DOCUMENT', $record->name],
            'package' => [$gemini->buildPackageEmbeddingText($record), 'RETRIEVAL_DOCUMENT', $record->name],
            'user' => [$record->userPreference ? $gemini->buildPreferenceEmbeddingText($record->userPreference) : null, 'RETRIEVAL_QUERY', null],
        };
    }
}
