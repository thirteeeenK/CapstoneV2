<?php

namespace App\Services\Chat;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ChatRetrievalService
{
    public const CATALOGS = ['retrieved_rooms' => 'room', 'retrieved_hotels' => 'hotel', 'retrieved_activities' => 'activity', 'retrieved_packages' => 'package', 'retrieved_addons' => 'addon'];

    public function __construct(private GeminiService $gemini) {}

    /** Existing handlers prepare deterministic cards/context with generation suspended by the orchestrator.
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    public function retrieve(array $task, callable $dispatch, float $deadline): array
    {
        $started = microtime(true);
        $this->gemini->lastContextPrices = [];
        $this->gemini->lastRetrievalMode = 'none';
        try {
            $data = $started >= $deadline
                ? ['reply' => 'This part could not finish in time. Please try it again.', 'retrieval_outcome' => RetrievalOutcome::make(RetrievalOutcome::TemporarilyUnavailable, 'turn_deadline')]
                : $dispatch();
        } catch (Throwable $exception) {
            Log::warning('chat_task_retrieval_failed', ['type' => $task['type'], 'exception' => $exception::class]);
            $data = ['reply' => 'I could not check this part right now. The other results are still available; please try this part again.', 'retrieval_outcome' => RetrievalOutcome::make(RetrievalOutcome::TemporarilyUnavailable, 'retrieval_failed')];
        }
        $references = [];
        // An itinerary keeps every choice that survived budget and preference
        // checks; only the standalone preview cards are capped for display.
        $eligible = ['retrieved_rooms' => array_column($data['retrieved_rooms'] ?? [], 'id'), 'retrieved_activities' => array_column($data['retrieved_activities'] ?? [], 'id')];
        foreach (self::CATALOGS as $key => $type) {
            if (! empty($data[$key])) {
                $data[$key] = array_slice($data[$key], 0, max(1, min(5, (int) config('services.chatbot.cards_per_task', 3))));
                foreach ($data[$key] as $card) {
                    $references[] = ['type' => $type, 'id' => (int) $card['id']];
                }
            }
        }
        if (! empty($data['itinerary_suggestion']) && empty($data['itinerary'])) {
            $ids = $eligible['retrieved_activities'];
            $suggestion = &$data['itinerary_suggestion'];
            $roomIds = $eligible['retrieved_rooms'];
            $suggestion['choices'] = array_values(array_filter($suggestion['choices'], fn (array $choice): bool => $choice['type'] === 'room' ? in_array($choice['id'], $roomIds, true) : ($choice['type'] !== 'activity' || in_array($choice['id'], $ids, true))));
            if (! empty($suggestion['accommodation']) && ! in_array($suggestion['accommodation']['room_id'], $roomIds, true)) {
                $suggestion['accommodation'] = null;
                $suggestion['accommodation_notice'] = 'No matching room remains after applying your preferences and budget. Adjust your stay preferences to see alternatives.';
            }
            foreach ($suggestion['days'] as &$day) {
                $day['choices'] = array_values(array_filter($suggestion['choices'], fn (array $choice): bool => $choice['day'] === $day['day']));
            }
            unset($day);
            // A total that still counts a removed activity or stay would be wrong; withdraw it.
            if (! empty($suggestion['cost'])) {
                $kept = array_column(array_filter($suggestion['choices'], fn (array $choice): bool => $choice['type'] === 'activity'), 'id');
                $removed = array_diff(array_column($suggestion['cost']['activity_lines'], 'id'), $kept) !== [] || ($suggestion['cost']['stay'] !== null && empty($suggestion['accommodation']));
                if ($removed) {
                    $previousSummary = $suggestion['cost']['summary'] ?? '';
                    $suggestion['cost'] = ['total' => null, 'status' => 'unknown', 'activity_lines' => [], 'stay' => null, 'activities' => null, 'budget' => $suggestion['cost']['budget'],
                        'summary' => 'Some choices were removed by your preferences or budget, so there is no total yet. Update the trip details for a new estimate.'] + $suggestion['cost'];
                    $suggestion['estimate'] = null;
                    if ($previousSummary !== '' && is_string($data['reply'] ?? null)) {
                        $data['reply'] = str_replace($previousSummary, $suggestion['cost']['summary'], $data['reply']);
                    }
                }
            }
            unset($suggestion);
        }
        $outcome = $data['retrieval_outcome'] ?? RetrievalOutcome::make(RetrievalOutcome::Matched, 'verified_result_found');
        $fallback = trim((string) ($data['reply'] ?? ''));
        if ($references !== []) {
            $count = count($references);
            $fallback = preg_replace('/I found \d+ verified options?\./u', 'I found '.$count.' verified '.($count === 1 ? 'option.' : 'options.'), $fallback);
        }
        if ($fallback === '') {
            $fallback = match (true) {
                ! empty($data['itinerary']) => 'I prepared a suggested itinerary from the available catalog. Review the dates, included items, and quoted total below; unlisted expenses are not included.',
                ! empty($data['weather']) => 'Review the latest weather information supplied below.',
                ! empty($data['discount_rules']) => $data['discount_rules'],
                default => 'I do not have enough verified detail to answer this part yet. Please specify what you would like me to check.',
            };
        }

        return [...$task, 'outcome' => $outcome, 'recovery' => RetrievalOutcome::recoveryKind($outcome), 'answer' => $fallback, 'data' => $data, 'card_refs' => $references, 'evidence' => [
            'prices' => $this->gemini->lastContextPrices,
            'retrieval_mode' => $this->gemini->lastRetrievalMode,
            'retrieved_at' => now()->toIso8601String(),
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
        ]];
    }
}
