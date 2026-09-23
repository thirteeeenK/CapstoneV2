<?php

namespace App\Console\Commands;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use App\Models\User;
use App\Services\Chat\ChatbotService;
use App\Services\Chat\IntentRouter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\ChatEvalMetrics;

#[Signature('chatbot:eval {--update-baseline : Write the baseline file from this run instead of comparing}')]
#[Description('Run the offline chatbot retrieval eval suite (seeded fixtures, stubbed LLM) and compare against the baseline')]
class ChatbotEvalCommand extends Command
{
    private const DIMS = 3072;

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

    /** @var array<string, array<string, int>> catalog => name => id */
    private array $nameIds = [];

    /** @var int[] */
    private array $sessionIds = [];

    private ?User $evalUser = null;

    /** @var array<string, int[]> model class => ids */
    private array $seededIds = [];

    public function handle(): int
    {
        Http::fake([
            '*embedContent*' => Http::response(['embedding' => ['values' => $this->unitVector(0)]]),
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
            $this->seed();
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
            $this->cleanup();
        }
    }

    private function runCase(array $case): array
    {
        $user = ($case['auth'] ?? null) === 'personalized' ? $this->evalUser : null;
        $session = ChatSession::create([
            'session_token' => ChatSession::generateToken(),
            'user_id' => $user?->id,
        ]);
        $this->sessionIds[] = $session->id;

        $service = app(ChatbotService::class);
        if (isset($case['setup']['prior_message'])) {
            $service->handle($session, $user, (string) $case['setup']['prior_message']);
        }

        $start = microtime(true);
        $reply = $service->handle($session, $user, (string) $case['query']);
        $latencyMs = (microtime(true) - $start) * 1000;

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

        $expectedIds = $this->mapNames($case['expect_result_names_any'] ?? []);
        $forbidIds = $this->mapNames($case['expect_forbid_names'] ?? []);

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
            'latency_ms' => $latencyMs,
        ];
    }

    /** @return int[] */
    private function mapNames(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            foreach ($this->nameIds as $catalog) {
                if (isset($catalog[$name])) {
                    $ids[] = $catalog[$name];
                }
            }
        }

        return array_values(array_unique($ids));
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
            'latency_p50_ms' => $metrics::latencyP50($rows),
            'latency_p95_ms' => $metrics::latencyP95($rows),
        ];
    }

    /** @return string[] */
    private function compare(array $metrics, array $baseline): array
    {
        $higherBetter = ['intent_accuracy', 'recall_at_3', 'recall_at_5', 'mrr', 'destination_match_rate', 'facts_hit_rate'];
        $lowerBetter = ['wrong_destination_rate', 'no_result_rate', 'forbid_hit_rate'];
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

    private function seed(): void
    {
        $boracay = $this->track(DestinationModel::factory()->create(['name' => 'Eval Boracay']));
        $elnido = $this->track(DestinationModel::factory()->create(['name' => 'Eval El Nido']));
        $cebu = $this->track(DestinationModel::factory()->create(['name' => 'Eval Cebu']));

        $palm = $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Palm Resort', 'destination_id' => $boracay->id,
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Palm Resort');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Bay Hotel', 'destination_id' => $boracay->id,
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.8),
        ]), 'Eval Bay Hotel');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Dunes Lodge', 'destination_id' => $boracay->id,
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.65),
        ]), 'Eval Dunes Lodge');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Cliff Lodge', 'destination_id' => $elnido->id,
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Cliff Lodge');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Hidden Resort', 'destination_id' => $boracay->id,
            'is_shown' => false, 'embedding' => $this->vectorString(0),
        ]), 'Eval Hidden Resort');

        $this->track(RoomType::factory()->create([
            'hotel_id' => $palm->id, 'room_name' => 'Eval Standard Room',
            'base_price' => 3000, 'base_occupancy' => 2, 'max_occupancy' => 2,
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Standard Room');
        $this->track(RoomType::factory()->create([
            'hotel_id' => $palm->id, 'room_name' => 'Eval Family Suite',
            'base_price' => 4500, 'base_occupancy' => 2, 'max_occupancy' => 4,
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.8),
        ]), 'Eval Family Suite');
        $this->track(RoomType::factory()->create([
            'hotel_id' => $palm->id, 'room_name' => 'Eval Flexible Room',
            'base_price' => 3500, 'base_occupancy' => 4, 'max_occupancy' => null,
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.65),
        ]), 'Eval Flexible Room');

        $this->track(ActivityModel::factory()->create([
            'activity_name' => 'Eval Paddle Tour', 'destination_id' => $boracay->id,
            'rate' => '500', 'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Paddle Tour');
        $this->track(ActivityModel::factory()->create([
            'activity_name' => 'Eval Cliff Dive', 'destination_id' => $elnido->id,
            'rate' => '800', 'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Cliff Dive');

        $this->track(Package::factory()->create([
            'name' => 'Eval Weekend Escape', 'destination_id' => $boracay->id,
            'price' => 12000, 'is_active' => true,
            'embedding' => $this->vectorString(0),
        ]), 'Eval Weekend Escape');

        $this->track(AddOnModel::factory()->create([
            'name' => 'Eval Airport Transfer', 'destination_id' => $boracay->id,
            'embedding' => $this->vectorString(0),
        ]), 'Eval Airport Transfer');

        $faq = Faq::create([
            'question' => 'What payment methods do you accept?',
            'answer' => 'We accept GCash, Maya, and all major credit cards.',
            'keywords' => 'payment, pay, bill, methods, gcash',
            'category' => 'Payments',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $faq->forceFill(['embedding' => $this->vectorString(0)])->save();
        $this->seededIds[Faq::class][] = $faq->id;

        $this->evalUser = User::factory()->create(['email' => 'eval-harness@example.com']);
        $this->evalUser->preferences_embedding = '['.implode(',', array_fill(0, self::DIMS, '0.01')).']';
        $this->evalUser->save();
    }

    private function track(object $model, ?string $name = null): object
    {
        $this->seededIds[$model::class][] = $model->id;
        if ($name !== null) {
            $catalog = match ($model::class) {
                HotelModel::class => 'hotels',
                RoomType::class => 'rooms',
                ActivityModel::class => 'activities',
                Package::class => 'packages',
                AddOnModel::class => 'addons',
                default => 'misc',
            };
            $this->nameIds[$catalog][$name] = $model->id;
        }

        return $model;
    }

    private function cleanup(): void
    {
        if ($this->sessionIds !== []) {
            SupportInquiry::whereIn('chat_session_id', $this->sessionIds)->delete();
            ChatMessage::whereIn('chat_session_id', $this->sessionIds)->delete();
            ChatSession::whereIn('id', $this->sessionIds)->delete();
        }
        foreach (array_reverse($this->seededIds) as $class => $ids) {
            $class::whereIn('id', $ids)->delete();
        }
        $this->evalUser?->delete();
    }

    /** @return float[] */
    private function unitVector(int $hotIndex): array
    {
        $v = array_fill(0, self::DIMS, 0.0);
        $v[$hotIndex] = 1.0;

        return $v;
    }

    private function vectorString(int $hotIndex): string
    {
        return '['.implode(',', $this->unitVector($hotIndex)).']';
    }

    private function gradedVectorString(float $x): string
    {
        $v = array_fill(0, self::DIMS, 0.0);
        $v[0] = $x;
        $v[1] = sqrt(max(0.0, 1.0 - $x * $x));

        return '['.implode(',', $v).']';
    }
}
