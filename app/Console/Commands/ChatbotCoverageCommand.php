<?php

namespace App\Console\Commands;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\KnowledgeArticle;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use App\Models\User;
use App\Services\Chat\ChatbotService;
use App\Services\GeminiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\CoverageScorer;

#[Signature('chatbot:coverage
    {--split=all : Run only the dev or heldout split}
    {--case=* : Run only these case ids}
    {--live : Use the configured Gemini embedding and chat models instead of stubs. PAID: about 2-6 provider calls per turn plus one per seeded record (roughly 400-800 calls for the full suite)}
    {--max-calls=400 : Live mode only: abort once this many outbound provider requests were sent}
    {--repeat=1 : Run each case this many times and report run-to-run variation}
    {--update-baseline : Record this run\'s passing cases as the baseline instead of comparing}
    {--fixture=tests/Fixtures/chatbot_coverage_v1.json : Benchmark fixture path, relative to the project root}')]
#[Description('Run the conversational coverage benchmark against an isolated synthetic catalog and report failures by class')]
class ChatbotCoverageCommand extends Command
{
    private const DIMS = 3072;

    private const ACTORS = ['user_a', 'user_b'];

    /** @var array<string, array<string, int>> card type => name => id */
    private array $nameIds = [];

    /** @var array<string, int> destination key => id */
    private array $destinationIds = [];

    /** @var array<class-string, list<int>> */
    private array $seeded = [];

    /** @var list<int> */
    private array $sessionIds = [];

    /** @var array<string, User> */
    private array $users = [];

    private bool $providersDown = false;

    private int $providerCalls = 0;

    public function handle(): int
    {
        $live = (bool) $this->option('live');
        // The benchmark seeds and deletes its own catalog; production or
        // development listings would also contaminate its ground truth.
        if (! app()->environment('testing')) {
            $this->error('Run the coverage benchmark against the testing database: php artisan chatbot:coverage --env=testing');

            return self::FAILURE;
        }
        $fixture = json_decode((string) File::get(base_path((string) $this->option('fixture'))), true, flags: JSON_THROW_ON_ERROR);
        $cases = $this->selectCases($fixture['cases']);
        $repeat = max(1, min(5, (int) $this->option('repeat')));

        if ($live) {
            if (! filled(config('services.gemini.api_key'))) {
                $this->error('Live mode needs GEMINI_API_KEY for the testing environment (for example in .env.testing).');

                return self::FAILURE;
            }
            $this->warn('Live mode: benchmark messages and the synthetic catalog are sent to the configured providers. This incurs API cost.');
            Event::listen(RequestSending::class, function (): void {
                if (++$this->providerCalls > (int) $this->option('max-calls')) {
                    throw new RuntimeException('Live coverage run stopped: --max-calls reached.');
                }
            });
        } else {
            $this->fakeProviders();
        }

        $configuration = $this->pinConfiguration($live);
        $started = now();

        try {
            $this->seed($fixture['catalog']);
            if ($live) {
                $this->embedSeededCatalog();
            }
            $runs = [];
            for ($run = 1; $run <= $repeat; $run++) {
                $runs[] = array_map(fn (array $case): array => $this->runCase($case), $cases);
            }
        } finally {
            $this->cleanup();
            $this->restoreConfiguration($configuration['previous']);
        }

        $rows = $runs[0];
        $summary = CoverageScorer::summarize($rows);
        $unstable = [];
        foreach ($rows as $i => $row) {
            $outcomes = array_unique(array_map(fn (array $runRows): bool => $runRows[$i]['passed'], $runs));
            if (count($outcomes) > 1) {
                $unstable[] = $row['id'];
            }
        }
        $summary['repeat'] = $repeat;
        $summary['unstable_cases'] = $unstable;

        $matrix = array_map(function (array $entry) use ($summary): array {
            return [...$entry, 'measured_pass_rate' => $summary['categories'][$entry['category']]['pass_rate'] ?? null];
        }, $fixture['coverage_matrix'] ?? []);

        $report = [
            'meta' => [
                'fixture' => $fixture['version'],
                'fixture_sha256' => hash_file('sha256', base_path((string) $this->option('fixture'))),
                'mode' => $live ? 'live' : 'offline_stubbed',
                'started_at' => $started->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'configuration' => $configuration['pinned'],
                'knowledge_supported' => $this->knowledgeSupported(),
                'provider_calls' => $live ? $this->providerCalls : null,
                'cost_note' => $live ? 'Live run: provider usage is billed to the configured API keys.' : 'Offline: providers stubbed, no external calls.',
                'split' => $this->option('split'),
            ],
            'summary' => $summary,
            'coverage_matrix' => $matrix,
            'cases' => $rows,
        ];

        File::ensureDirectoryExists(storage_path('app/eval'));
        $filename = 'chatbot-coverage-'.($live ? 'live-' : '').now()->format('Y-m-d_His').'.json';
        File::put(storage_path('app/eval/'.$filename), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

        $this->printSummary($summary, $matrix, $rows);
        $this->line('Wrote storage/app/eval/'.$filename);

        return $this->compareBaseline($rows, $live, $fixture['version']);
    }

    /** @param list<array<string, mixed>> $cases
     * @return list<array<string, mixed>>
     */
    private function selectCases(array $cases): array
    {
        $split = (string) $this->option('split');
        $only = (array) $this->option('case');

        return array_values(array_filter($cases, fn (array $case): bool => ($split === 'all' || $case['split'] === $split)
            && ($only === [] || in_array($case['id'], $only, true))));
    }

    private function fakeProviders(): void
    {
        Http::fake(function ($request) {
            if ($this->providersDown) {
                return Http::response(['error' => ['message' => 'Simulated provider outage']], 503);
            }
            $url = $request->url();
            if (str_contains($url, 'embedContent')) {
                $vector = $this->unitVector();

                return Http::response(['embedding' => ['values' => $vector], 'embeddings' => [['values' => $vector]]]);
            }
            if (str_contains($url, 'generateContent')) {
                return Http::response([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'Coverage reply stub.']]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
                ]);
            }

            // Weather, routing and backup-model calls are unavailable offline;
            // the benchmark measures how honestly the bot reports that.
            return Http::response(['error' => 'Offline coverage run'], 503);
        });
    }

    /** @return array{pinned: array<string, mixed>, previous: array<string, mixed>} */
    private function pinConfiguration(bool $live): array
    {
        $pinned = [
            'services.chatbot.conversational_enabled' => true,
            'services.chatbot.guided_conversation_enabled' => true,
            'services.chatbot.planner_enabled' => true,
        ];
        // Requests never leave the process offline (Http::fake), but the
        // services skip provider calls entirely without a configured key.
        if (! $live) {
            $pinned['services.gemini.api_key'] = 'offline-coverage-stub';
        }
        $previous = [];
        foreach ($pinned as $key => $value) {
            $previous[$key] = config($key);
        }
        config($pinned);

        return [
            'pinned' => [
                ...array_diff_key($pinned, ['services.gemini.api_key' => true]),
                'services.chatbot.max_tasks' => config('services.chatbot.max_tasks'),
                'services.chatbot.cards_per_task' => config('services.chatbot.cards_per_task'),
                'services.chatbot.turn_timeout' => config('services.chatbot.turn_timeout'),
                'services.gemini.chat_model' => config('services.gemini.chat_model'),
                'services.gemini.embedding_model' => config('services.gemini.embedding_model'),
            ],
            'previous' => $previous,
        ];
    }

    /** @param array<string, mixed> $previous */
    private function restoreConfiguration(array $previous): void
    {
        config($previous);
    }

    private function knowledgeSupported(): bool
    {
        return class_exists(KnowledgeArticle::class);
    }

    private function sourceAvailable(string $source): bool
    {
        return $source !== 'knowledge' || $this->knowledgeSupported();
    }

    /** @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    private function runCase(array $case): array
    {
        $this->providersDown = ($case['providers'] ?? 'up') === 'down';
        $sessions = [];
        $priorCards = [];
        $turnRows = [];
        $hidden = [];

        try {
            foreach ($case['turns'] as $index => $turn) {
                // A fresh service graph per case keeps request-scoped caches
                // (embeddings, prompts) from leaking between cases.
                if ($index === 0) {
                    app()->forgetInstance(GeminiService::class);
                    app()->forgetInstance(ChatbotService::class);
                }
                foreach ((array) ($turn['hide'] ?? []) as $name) {
                    $hidden[] = $this->setVisibility($name, false);
                }

                $actor = $turn['as'] ?? 'guest';
                $user = $this->users[$actor] ?? null;
                $sessions[$actor] ??= tap(ChatSession::create(['session_token' => ChatSession::generateToken(), 'user_id' => $user?->id]), fn (ChatSession $s) => $this->sessionIds[] = $s->id);

                $started = microtime(true);
                $reply = app(ChatbotService::class)->handle($sessions[$actor]->refresh(), $user, (string) $turn['say']);
                $latency = round((microtime(true) - $started) * 1000, 1);

                $observed = CoverageScorer::observe($reply);
                $priorCards[$index + 1] = $observed['cards'];
                $expect = $turn['expect'] ?? null;
                $score = $expect === null ? null : CoverageScorer::scoreTurn(
                    $this->resolveExpectation($expect),
                    $observed,
                    $priorCards,
                    $this->sourceAvailable($case['source'])
                );

                $turnRows[] = [
                    'turn' => $index + 1,
                    'as' => $actor,
                    'message' => $turn['say'],
                    'checked' => $expect !== null,
                    'passed' => $score['passed'] ?? true,
                    'failure' => $score['failure'] ?? null,
                    'failed_checks' => $score['failed_checks'] ?? [],
                    'task_checks' => array_values($score['task_checks'] ?? []),
                    'constraint' => (bool) ($expect['constraint'] ?? false),
                    'outcome' => $observed['outcome'],
                    'tasks' => $observed['tasks'],
                    'cards' => $this->cardNames($observed['typed']),
                    'constraints' => array_diff_key(array_filter($observed['constraints'][0] ?? [], fn (mixed $v): bool => $v !== null && $v !== [] && $v !== false), ['place_names' => true]),
                    'sections' => array_map(fn (array $section): array => [
                        'type' => $section['type'] ?? null,
                        'status' => $section['outcome']['status'] ?? null,
                        'reason' => $section['outcome']['reason'] ?? null,
                        'query' => $section['query'] ?? null,
                    ], $reply['sections'] ?? []),
                    'outcome_reason' => $reply['retrieval_outcome']['reason'] ?? null,
                    'forbidden_claims' => CoverageScorer::forbiddenClaims($observed['text']),
                    'reply_excerpt' => Str::limit(preg_replace('/\s+/u', ' ', $observed['text']), 400),
                    'latency_ms' => $latency,
                ];
            }
        } finally {
            foreach ($hidden as $restore) {
                $restore();
            }
            $this->providersDown = false;
        }

        $checked = array_values(array_filter($turnRows, fn (array $t): bool => $t['checked']));
        $failed = array_values(array_filter($checked, fn (array $t): bool => ! $t['passed']));
        $last = end($case['turns']);

        return [
            'id' => $case['id'],
            'category' => $case['category'],
            'split' => $case['split'],
            'lang' => $case['lang'],
            'source' => $case['source'],
            'passed' => $failed === [],
            'failure' => $failed[0]['failure'] ?? null,
            'answerable' => CoverageScorer::expectsAnswer($last['expect'] ?? []),
            'turns' => $turnRows,
        ];
    }

    /** @param array<string, mixed> $expect
     * @return array<string, mixed>
     */
    private function resolveExpectation(array $expect): array
    {
        foreach (['any', 'all', 'none'] as $key) {
            if (isset($expect[$key])) {
                $expect[$key] = array_map(fn (string $name): string => $this->typedId($name), $expect[$key]);
            }
        }

        return $expect;
    }

    private function typedId(string $name): string
    {
        foreach ($this->nameIds as $type => $names) {
            if (isset($names[$name])) {
                return $type.':'.$names[$name];
            }
        }

        throw new RuntimeException("Coverage fixture references unknown record '{$name}'.");
    }

    /** @param list<string> $typed
     * @return list<string>
     */
    private function cardNames(array $typed): array
    {
        $lookup = [];
        foreach ($this->nameIds as $type => $names) {
            foreach ($names as $name => $id) {
                $lookup[$type.':'.$id] = $name;
            }
        }

        return array_map(fn (string $id): string => $lookup[$id] ?? $id, $typed);
    }

    /** @return callable(): void restores the original visibility */
    private function setVisibility(string $name, bool $visible): callable
    {
        [$type, $id] = explode(':', $this->typedId($name));
        [$model, $column] = match ($type) {
            'hotel' => [HotelModel::class, 'is_shown'],
            'room' => [RoomType::class, 'is_shown'],
            'activity' => [ActivityModel::class, 'is_shown'],
            'package' => [Package::class, 'is_active'],
            'addon' => [AddOnModel::class, 'is_shown'],
        };
        $record = $model::findOrFail((int) $id);
        $original = $record->{$column};
        $record->forceFill([$column => $visible])->save();

        return fn () => $record->forceFill([$column => $original])->save();
    }

    /** @param array<string, mixed> $catalog */
    private function seed(array $catalog): void
    {
        foreach ($catalog['destinations'] as $destination) {
            $model = $this->track(DestinationModel::factory()->create(['name' => $destination['name'], 'region' => $destination['region']]));
            $this->destinationIds[$destination['key']] = $model->id;
        }

        foreach ($catalog['hotels'] as $hotel) {
            $this->track(HotelModel::factory()->create([
                'hotel_name' => $hotel['name'],
                'destination_id' => $this->destinationIds[$hotel['destination']],
                'type' => $hotel['type'],
                'featured_amenities' => $hotel['featured_amenities'],
                'vibe_tags' => $hotel['vibe_tags'],
                'hotel_description' => $hotel['hotel_description'],
                'is_shown' => $hotel['is_shown'] ?? true,
                'embedding' => $this->vectorString($hotel['vector']),
            ]), 'hotel', $hotel['name']);
        }

        foreach ($catalog['rooms'] as $room) {
            $this->track(RoomType::factory()->create([
                'hotel_id' => $this->nameIds['hotel'][$room['hotel']],
                'room_name' => $room['name'],
                'base_price' => $room['base_price'],
                'base_occupancy' => $room['base_occupancy'],
                'max_occupancy' => $room['max_occupancy'],
                'view_type' => $room['view_type'],
                'description' => $room['view_type'].' room at '.$room['hotel'].'.',
                'is_shown' => $room['is_shown'] ?? true,
                'embedding' => $this->vectorString($room['vector']),
            ]), 'room', $room['name']);
        }

        foreach ($catalog['activities'] as $activity) {
            $this->track(ActivityModel::factory()->create([
                'activity_name' => $activity['name'],
                'destination_id' => $this->destinationIds[$activity['destination']],
                'category' => $activity['category'],
                'activity_level' => $activity['activity_level'],
                'rate' => $activity['rate'],
                'description' => $activity['description'],
                'requirements' => $activity['requirements'] ?? null,
                'is_shown' => $activity['is_shown'] ?? true,
                'embedding' => $this->vectorString($activity['vector']),
            ]), 'activity', $activity['name']);
        }

        foreach ($catalog['packages'] as $package) {
            $this->track(Package::factory()->create([
                'name' => $package['name'],
                'destination_id' => $this->destinationIds[$package['destination']],
                'price' => $package['price'],
                'days' => $package['days'],
                'nights' => $package['nights'],
                'valid_to' => $package['valid_to'] ?? null,
                'is_active' => true,
                'embedding' => $this->vectorString($package['vector']),
            ]), 'package', $package['name']);
        }

        foreach ($catalog['addons'] as $addon) {
            $this->track(AddOnModel::factory()->create([
                'name' => $addon['name'],
                'destination_id' => $this->destinationIds[$addon['destination']],
                'type' => $addon['type'],
                'description' => $addon['description'],
                'embedding' => $this->vectorString($addon['vector']),
            ]), 'addon', $addon['name']);
        }

        foreach ($catalog['faqs'] as $i => $faq) {
            $model = Faq::create([...$faq, 'sort_order' => $i, 'is_active' => true, 'show_on_landing' => false]);
            $model->forceFill(['embedding' => $this->vectorString(0.5)])->save();
            $this->track($model);
        }

        if ($this->knowledgeSupported()) {
            foreach ($catalog['knowledge_articles'] ?? [] as $article) {
                $this->track(KnowledgeArticle::create([
                    'title' => $article['title'],
                    'body' => $article['body'],
                    'category' => $article['category'],
                    'destination_id' => $this->destinationIds[$article['destination']] ?? null,
                    'source_label' => $article['source_label'],
                    'status' => $article['status'],
                    'reviewed_at' => now()->subWeek()->toDateString(),
                    'expires_at' => ! empty($article['expired']) ? now()->subDay()->toDateString() : null,
                ]));
            }
        }

        foreach (self::ACTORS as $actor) {
            $this->users[$actor] = User::factory()->create(['email' => 'coverage-'.$actor.'-'.Str::uuid().'@example.com']);
        }
    }

    /**
     * Live mode ranks with real query embeddings, so the seeded records need
     * real document embeddings too; stub vectors would make ranking noise.
     */
    private function embedSeededCatalog(): void
    {
        $gemini = app(GeminiService::class);
        $texts = [
            HotelModel::class => fn (HotelModel $m): string => $gemini->buildHotelEmbeddingText($m, $m->destination?->name),
            RoomType::class => fn (RoomType $m): string => $gemini->buildRoomEmbeddingText($m, $m->hotel?->hotel_name, $m->hotel?->destination?->name),
            ActivityModel::class => fn (ActivityModel $m): string => $gemini->buildActivityEmbeddingText($m, $m->destination?->name),
            Package::class => fn (Package $m): string => $gemini->buildPackageEmbeddingText($m),
            AddOnModel::class => fn (AddOnModel $m): string => $gemini->buildAddOnEmbeddingText($m, $m->destination?->name),
            Faq::class => fn (Faq $m): string => $gemini->buildFaqEmbeddingText($m),
            KnowledgeArticle::class => fn (KnowledgeArticle $m): string => $m->embeddingText(),
        ];
        foreach ($texts as $class => $text) {
            foreach ($class::whereKey($this->seeded[$class] ?? [])->get() as $model) {
                $body = $text($model);
                $vector = $gemini->formatVectorForDb($gemini->generateEmbedding($body, 'RETRIEVAL_DOCUMENT'));
                $extra = $class === KnowledgeArticle::class
                    ? ['embedding_source_hash' => sha1($body.config('services.gemini.embedding_model')), 'embedding_model' => config('services.gemini.embedding_model'), 'embedded_at' => now()]
                    : [];
                $model->forceFill(['embedding' => $vector, ...$extra])->save();
            }
        }
    }

    private function track(object $model, ?string $type = null, ?string $name = null): object
    {
        $this->seeded[$model::class][] = $model->id;
        if ($type !== null && $name !== null) {
            $this->nameIds[$type][$name] = $model->id;
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
        foreach (array_reverse($this->seeded, true) as $class => $ids) {
            $class::whereIn('id', $ids)->delete();
        }
        foreach ($this->users as $user) {
            $user->forceDelete();
        }
        $this->seeded = [];
        $this->sessionIds = [];
        $this->users = [];
    }

    /** @param list<array<string, mixed>> $rows
     * @return int exit code
     */
    private function compareBaseline(array $rows, bool $live, string $version): int
    {
        $path = storage_path('app/eval/coverage_baseline'.($live ? '_live' : '').'.json');
        $passing = array_values(array_column(array_filter($rows, fn (array $r): bool => $r['passed']), 'id'));

        if ($this->option('update-baseline')) {
            File::put($path, json_encode(['fixture' => $version, 'passing_cases' => $passing, 'recorded_at' => now()->toIso8601String()], JSON_PRETTY_PRINT));
            $this->info('Baseline updated: '.count($passing).' passing cases recorded.');

            return self::SUCCESS;
        }
        if (! File::exists($path)) {
            $this->warn('No coverage baseline yet; run with --update-baseline to record one.');

            return self::SUCCESS;
        }

        $baseline = json_decode((string) File::get($path), true);
        $ran = array_column($rows, 'id');
        $regressed = array_values(array_intersect(array_diff($baseline['passing_cases'] ?? [], $passing), $ran));
        foreach ($regressed as $id) {
            $this->error("Regression: {$id} passed in the baseline and fails now.");
        }

        return $regressed === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $summary */
    private function printSummary(array $summary, array $matrix, array $rows): void
    {
        $this->table(['split', 'cases', 'passed', 'substantive answers', 'correct abstentions'], collect($summary['splits'])->map(fn (array $s, string $split): array => [
            $split, $s['cases'], $s['passed'], $s['substantive_answer_rate'] ?? '—', $s['correct_abstention_rate'] ?? '—',
        ])->values()->all());
        $this->table(['category', 'cases', 'pass rate', 'missing data'], collect($matrix)->map(fn (array $m): array => [
            $m['category'], $summary['categories'][$m['category']]['cases'] ?? 0, $m['measured_pass_rate'] ?? '—', Str::limit((string) $m['missing_data'], 60),
        ])->all());
        $this->table(['failure class', 'turns'], collect($summary['failure_classes'])->map(fn (int $n, string $class): array => [$class, $n])->values()->all());
        $this->table(['gate', 'met'], collect($summary['gates'])->map(fn (bool $met, string $gate): array => [$gate, $met ? 'yes' : 'no'])->values()->all());
        $this->line(sprintf('Constraint/correction accuracy: %s · task accounting: %s · unsupported-claim turns: %d · latency p50/p95: %s/%s ms',
            $summary['constraint_correction_accuracy'] ?? '—', $summary['task_accounting_rate'] ?? '—', $summary['unsupported_claim_turns'],
            $summary['latency_p50_ms'] ?? '—', $summary['latency_p95_ms'] ?? '—'));
        foreach ($rows as $row) {
            if (! $row['passed']) {
                $turn = collect($row['turns'])->first(fn (array $t): bool => ! $t['passed']);
                $this->line("  ✗ {$row['id']} [{$row['failure']}] ".implode(', ', $turn['failed_checks'] ?? []));
            }
        }
    }

    /** @return list<float> */
    private function unitVector(): array
    {
        $vector = array_fill(0, self::DIMS, 0.0);
        $vector[0] = 1.0;

        return $vector;
    }

    /** Vector whose cosine similarity with the stubbed query embedding is $similarity. */
    private function vectorString(float $similarity): string
    {
        $vector = array_fill(0, self::DIMS, 0.0);
        $vector[0] = $similarity;
        $vector[1] = sqrt(max(0.0, 1.0 - $similarity * $similarity));

        return '['.implode(',', $vector).']';
    }
}
