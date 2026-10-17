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
        foreach (self::CATALOGS as $key => $type) {
            if (! empty($data[$key])) {
                $data[$key] = array_slice($data[$key], 0, max(1, min(5, (int) config('services.chatbot.cards_per_task', 3))));
                foreach ($data[$key] as $card) {
                    $references[] = ['type' => $type, 'id' => (int) $card['id']];
                }
            }
        }
        $outcome = $data['retrieval_outcome'] ?? RetrievalOutcome::make(RetrievalOutcome::Matched, 'verified_result_found');
        $fallback = trim((string) ($data['reply'] ?? ''));
        if ($fallback === '') {
            $fallback = match (true) {
                ! empty($data['itinerary']) => 'I prepared a suggested itinerary from the available catalog. Review the dates, included items, and quoted total below; unlisted expenses are not included.',
                ! empty($data['weather']) => 'Review the latest weather information supplied below.',
                ! empty($data['discount_rules']) => $data['discount_rules'],
                default => 'I do not have enough verified detail to answer this part yet. Please specify what you would like me to check.',
            };
        }

        return [...$task, 'outcome' => $outcome, 'answer' => $fallback, 'data' => $data, 'card_refs' => $references, 'evidence' => [
            'prices' => $this->gemini->lastContextPrices,
            'retrieval_mode' => $this->gemini->lastRetrievalMode,
            'retrieved_at' => now()->toIso8601String(),
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
        ]];
    }
}
