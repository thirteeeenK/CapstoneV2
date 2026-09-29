<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatEvalHarness;
use App\Services\Chat\ChatJudgeEvidenceContext;
use App\Services\ChatbotJudgeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Helper\Table;

#[Signature('chatbot:judge {--export= : Write per-case judge CSV to path (relative to project root)}')]
#[Description('LLM-as-judge (RAGAS-style, Gemini): faithfulness/answer-relevancy/answer-correctness 1-4 over the chatbot eval fixtures. Needs GEMINI_API_KEY and real API calls.')]
class ChatbotJudgeCommand extends Command
{
    public function handle(ChatbotJudgeService $judge, ChatJudgeEvidenceContext $evidence): int
    {
        if (! config('services.gemini.api_key')) {
            $this->error('GEMINI_API_KEY is not set — chatbot:judge needs real Gemini calls. Add it to .env and retry.');

            return self::FAILURE;
        }

        $cases = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/chatbot_eval_v1.json')),
            true
        );

        // Only embedContent is stubbed. The seeded fixture rows carry synthetic
        // one-hot vectors, so a real query embedding shares no direction with
        // them and pgvector rejects every candidate — retrieval would come back
        // empty and the judge would score nothing but abstentions. Pinning the
        // query embedding keeps retrieval identical to the offline suite the
        // fixture ground truth was authored against, while generateContent
        // still hits Gemini for real (non-matching URLs pass through, since
        // preventStrayRequests defaults to false).
        Http::fake([
            '*embedContent*' => Http::response([
                'embedding' => ['values' => (new ChatEvalHarness)->unitVector(0)],
            ]),
        ]);

        $harness = new ChatEvalHarness;
        $scored = [];
        $skipped = [];
        $excluded = [];
        $rows = [];

        try {
            $harness->seed();

            foreach ($cases as $index => $case) {
                $id = (string) ($case['id'] ?? "case-{$index}");
                if (($case['judge_eligible'] ?? true) === false) {
                    $excluded[] = $id;

                    continue;
                }
                $this->line(sprintf('Judging %d/%d: %s ...', $index + 1, count($cases), $id));

                $reply = $harness->ask($case)['reply'];
                $result = $judge->scoreCase(
                    $id,
                    (string) $case['query'],
                    $this->answerText($reply),
                    $evidence->build($reply),
                    $this->reference($case),
                );

                if (isset($result['error'])) {
                    $skipped[] = "{$id} ({$result['error']})";

                    continue;
                }

                $scored[] = $result;
                $rows[] = [
                    $id,
                    $result['faithfulness'],
                    $result['answer_relevancy'],
                    $result['answer_correctness'] ?? 'n/a',
                    $result['reasoning'],
                ];
            }
        } finally {
            $harness->cleanup();
        }

        if ($rows === []) {
            $this->warn('No cases scored. Skipped:');
            foreach ($skipped as $s) {
                $this->line("  - {$s}");
            }

            return self::FAILURE;
        }

        $macro = $judge->macroMeans($scored);

        $table = new Table($this->output);
        $table->setHeaders(['case', 'faithfulness', 'answer_relevancy', 'answer_correctness', 'reasoning']);
        $table->setRows($rows);
        $table->render();

        $this->line(sprintf(
            'Macro over %d case(s) [model %s]: faithfulness=%.2f/4  answer_relevancy=%.2f/4  answer_correctness=%.2f/4 (n=%d)',
            $macro['n'],
            $scored[0]['model'],
            $macro['faithfulness'],
            $macro['answer_relevancy'],
            $macro['answer_correctness'] ?? 0.0,
            $macro['correctness_n'],
        ));
        $this->line('Correctness is scored only on cases with a reference answer; abstention cases are skipped, not failed.');
        if ($excluded !== []) {
            $this->line(sprintf('%d non-retrieval cases were excluded because their verified source context is not yet supplied to the judge.', count($excluded)));
        }

        foreach ($skipped as $s) {
            $this->warn("Skipped: {$s}");
        }

        File::ensureDirectoryExists(storage_path('app/eval'));
        $filename = 'chatbot-judge-'.now()->format('Y-m-d_His').'.json';
        $payload = ['model' => $scored[0]['model'], 'macro' => $macro, 'cases' => $scored, 'skipped' => $skipped, 'excluded' => $excluded];
        File::put(storage_path('app/eval/'.$filename), json_encode($payload, JSON_PRETTY_PRINT));
        $this->info("Wrote storage/app/eval/{$filename}");

        $export = $this->option('export');
        if (is_string($export) && $export !== '') {
            $path = str_starts_with($export, 'storage/') ? storage_path(substr($export, 8)) : base_path($export);
            File::ensureDirectoryExists(dirname($path));
            $fp = fopen($path, 'w');
            if ($fp === false) {
                $this->error("Cannot open {$path} for writing.");

                return self::FAILURE;
            }
            fputcsv($fp, ['case', 'faithfulness', 'answer_relevancy', 'answer_correctness', 'model', 'reasoning']);
            foreach ($scored as $s) {
                fputcsv($fp, [$s['id'], $s['faithfulness'], $s['answer_relevancy'], $s['answer_correctness'], $s['model'], $s['reasoning']]);
            }
            fputcsv($fp, []);
            fputcsv($fp, ['macro_faithfulness', $macro['faithfulness'], 'macro_answer_relevancy', $macro['answer_relevancy'], 'macro_answer_correctness', $macro['answer_correctness'], 'n', $macro['n']]);
            fclose($fp);
            $this->info("Wrote CSV to {$export} ({$path})");
        }

        return self::SUCCESS;
    }

    /** The prose the user actually saw, plus any itinerary blocks. */
    private function answerText(array $reply): string
    {
        $text = trim((string) ($reply['reply'] ?? ''));
        if (! empty($reply['itinerary'])) {
            $text .= "\n\nITINERARY:\n".json_encode($reply['itinerary'], JSON_PRETTY_PRINT);
        }

        return $text;
    }

    /**
     * The fixture's ground truth rendered as prose, or null when the case has
     * no reference — the abstention cases, where a null reference is correct
     * behaviour rather than a missing annotation.
     */
    private function reference(array $case): ?string
    {
        $facts = array_values(array_filter(array_map('strval', (array) ($case['required_facts'] ?? []))));
        $names = array_values(array_filter(array_map('strval', (array) ($case['expect_result_names_any'] ?? []))));

        $parts = [];
        if ($facts !== []) {
            $parts[] = 'The answer should convey these facts: '.implode('; ', $facts).'.';
        }
        if ($names !== []) {
            $parts[] = 'The answer should surface at least one of these results: '.implode(', ', $names).'.';
        }
        if (! empty($case['expect_destination'])) {
            $parts[] = 'Everything recommended must be in '.$case['expect_destination'].'.';
        }
        if (($case['expect_intent'] ?? null) !== null) {
            $parts[] = 'The user is asking for '.$case['expect_intent'].' — a different kind of answer is a mismatch.';
        }
        if (($case['expect_abstention'] ?? null) === true) {
            $parts[] = 'The catalog genuinely has nothing matching this query, so the correct answer is an honest statement that no result is available. Surfacing an unrelated result is incorrect.';
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
