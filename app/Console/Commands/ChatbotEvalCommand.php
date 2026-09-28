<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatEvalHarness;
use App\Services\Chat\ChatEvalMetrics;
use App\Services\Chat\IntentRouter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

#[Signature('chatbot:eval {--update-baseline : Write the baseline file from this run instead of comparing}')]
#[Description('Run the offline chatbot retrieval eval suite (seeded fixtures, stubbed LLM) and compare against the baseline')]
class ChatbotEvalCommand extends Command
{
    private const INTENT_GROUPS = [
        IntentRouter::HOTEL_SEARCH => ['hotels'],
        IntentRouter::ROOM_SEARCH => ['rooms'],
        IntentRouter::ACTIVITY_SEARCH => ['activities'],
        IntentRouter::PACKAGE_SEARCH => ['packages'],
        IntentRouter::ADDON_SEARCH => ['addons'],
    ];

    private const REPLY_GROUPS = [
        'hotels' => 'retrieved_hotels',
        'rooms' => 'retrieved_rooms',
        'activities' => 'retrieved_activities',
        'packages' => 'retrieved_packages',
        'addons' => 'retrieved_addons',
    ];

    private ChatEvalHarness $harness;

    public function handle(): int
    {
        $this->harness = new ChatEvalHarness;

        Http::fake([
            '*embedContent*' => Http::response(['embedding' => ['values' => $this->harness->unitVector(0)]]),
            '*generateContent*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Eval reply stub.']]],
                    'finishReason' => 'STOP',
                    'tokenMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
                ]],
            ]),
        ]);

        $cases = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/chatbot_eval_v1.json')),
            true
        );

        try {
            $this->harness->seed();
            $rows = [];
            foreach ($cases as $case) {
                $rows[] = $this->runCase($case);
            }
            $metrics = $this->metrics($rows);

            // Brief-literal output dir: storage/app/eval (the local disk root is
            // storage/app/private on Laravel 11, so Storage::disk('local')
            // would land files in storage/app/private/eval instead).
            File::ensureDirectoryExists(storage_path('app/eval'));
            $filename = 'chatbot-eval-'.now()->format('Y-m-d_His').'.json';
            File::put(storage_path('app/eval/'.$filename), json_encode(['metrics' => $metrics, 'cases' => $rows], JSON_PRETTY_PRINT));
            $this->line('Wrote storage/app/eval/'.$filename);
            $this->table(
                ['metric', 'value'],
                collect($metrics)->map(fn ($v, $k) => [$k, is_float($v) ? round($v, 4) : $v])->all()
            );

            if ($this->option('update-baseline')) {
                File::put(storage_path('app/eval/eval_baseline.json'), json_encode($metrics, JSON_PRETTY_PRINT));
                $this->info('Baseline updated.');

                return self::SUCCESS;
            }

            if (! File::exists(storage_path('app/eval/eval_baseline.json'))) {
                $this->warn('No baseline yet — run with --update-baseline to establish one.');

                return self::SUCCESS;
            }

            $baseline = json_decode((string) File::get(storage_path('app/eval/eval_baseline.json')), true);
            $regressions = $this->compare($metrics, $baseline);
            foreach ($regressions as $regression) {
                $this->error($regression);
            }

            return $regressions === [] ? self::SUCCESS : self::FAILURE;
        } finally {
            $this->harness->cleanup();
        }
    }

    private function runCase(array $case): array
    {
        $result = $this->harness->ask($case);
        $reply = $result['reply'];

        $trace = $reply['trace'] ?? [];
        $intent = $trace['intent'] ?? null;
        $groups = self::INTENT_GROUPS[$intent] ?? array_keys(self::REPLY_GROUPS);

        $retrievedIds = [];
        $destinations = [];
        $cardsHaystack = '';
        foreach ($groups as $group) {
            $key = self::REPLY_GROUPS[$group];
            foreach ((array) ($reply[$key] ?? []) as $card) {
                if (isset($card['id'])) {
                    $retrievedIds[] = (int) $card['id'];
                }
                if (! empty($card['destination'])) {
                    $destinations[] = (string) $card['destination'];
                }
            }
            $cardsHaystack .= ' '.json_encode($reply[$key] ?? []);
        }

        $expectedIds = $this->harness->mapNames($case['expect_result_names_any'] ?? []);
        $forbidIds = $this->harness->mapNames($case['expect_forbid_names'] ?? []);

        $haystack = (string) ($reply['reply'] ?? '').' '.$cardsHaystack.' '.json_encode($reply['itinerary'] ?? []);
        $factHits = 0;
        foreach ((array) ($case['required_facts'] ?? []) as $fact) {
            if (stripos($haystack, (string) $fact) !== false) {
                $factHits++;
            }
        }

        return [
            'id' => $case['id'],
            'intent' => $intent,
            'expect_intent' => $case['expect_intent'] ?? null,
            'retrieved_ids' => array_values(array_unique($retrievedIds)),
            'expect_ids' => $expectedIds,
            'forbid_ids' => $forbidIds,
            'destinations' => array_values(array_unique($destinations)),
            'expect_destination' => $case['expect_destination'] ?? null,
            'fact_hits' => $factHits,
            'fact_total' => count((array) ($case['required_facts'] ?? [])),
            'constraint_compliant' => empty(array_intersect($retrievedIds, $forbidIds)),
            'claims_grounded' => $factHits === count((array) ($case['required_facts'] ?? [])),
            'expect_abstention' => $case['expect_abstention'] ?? null,
            'abstained' => in_array($reply['retrieval_outcome']['status'] ?? null, ['no_match', 'needs_clarification', 'temporarily_unavailable'], true),
            'is_multi_turn' => isset($case['setup']['prior_message']),
            'latency_ms' => $result['latency_ms'],
        ];
    }

    private function metrics(array $rows): array
    {
        $metrics = ChatEvalMetrics::class;
        $forbidScoped = array_values(array_filter($rows, fn ($r) => ! empty($r['forbid_ids'])));
        $forbidHits = count(array_filter(
            $forbidScoped,
            fn ($r) => count(array_intersect($r['retrieved_ids'], $r['forbid_ids'])) > 0
        ));
        $factHits = array_sum(array_column($rows, 'fact_hits'));
        $factTotal = array_sum(array_column($rows, 'fact_total'));

        return [
            'cases' => count($rows),
            'intent_accuracy' => $metrics::intentAccuracy($rows),
            'recall_at_3' => $metrics::recallAt($rows, 3),
            'recall_at_5' => $metrics::recallAt($rows, 5),
            'mrr' => $metrics::mrr($rows),
            'destination_match_rate' => $metrics::destinationMatchRate($rows),
            'wrong_destination_rate' => $metrics::wrongDestinationRate($rows),
            'no_result_rate' => $metrics::noResultRate($rows),
            'forbid_hit_rate' => $forbidScoped === [] ? null : $forbidHits / count($forbidScoped),
            'facts_hit_rate' => $factTotal === 0 ? null : $factHits / $factTotal,
            'constraint_violation_rate' => $metrics::constraintViolationRate($rows),
            'unsupported_claim_rate' => $metrics::unsupportedClaimRate($rows),
            'valid_abstention_rate' => $metrics::validAbstentionRate($rows),
            'multi_turn_consistency_rate' => $metrics::multiTurnConsistencyRate($rows),
            'latency_p50_ms' => $metrics::latencyP50($rows),
            'latency_p95_ms' => $metrics::latencyP95($rows),
        ];
    }

    /** @return string[] */
    private function compare(array $metrics, array $baseline): array
    {
        $higherBetter = ['intent_accuracy', 'recall_at_3', 'recall_at_5', 'mrr', 'destination_match_rate', 'facts_hit_rate', 'valid_abstention_rate', 'multi_turn_consistency_rate'];
        $lowerBetter = ['wrong_destination_rate', 'no_result_rate', 'forbid_hit_rate', 'constraint_violation_rate', 'unsupported_claim_rate'];
        $regressions = [];

        foreach ($higherBetter as $key) {
            if (($metrics[$key] ?? null) !== null && ($baseline[$key] ?? null) !== null
                && $baseline[$key] > $metrics[$key] + 1e-9) {
                $regressions[] = "{$key} regressed: {$metrics[$key]} < baseline {$baseline[$key]}";
            }
        }
        foreach ($lowerBetter as $key) {
            if (($metrics[$key] ?? null) !== null && ($baseline[$key] ?? null) !== null
                && $metrics[$key] > $baseline[$key] + 1e-9) {
                $regressions[] = "{$key} regressed: {$metrics[$key]} > baseline {$baseline[$key]}";
            }
        }

        return $regressions;
    }
}
