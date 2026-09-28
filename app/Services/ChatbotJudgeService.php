<?php

namespace App\Services;

/**
 * LLM-as-judge for the chatbot eval fixtures, in the RAGAS metric style but
 * implemented in PHP against Gemini so it needs no Python sidecar.
 *
 * Three dimensions per case: faithfulness (are the claims supported by the
 * retrieved records), answer_relevancy (does it answer the question asked) and
 * answer_correctness (does it match the reference). Correctness is only scored
 * when the fixture supplies a reference — roughly a third of the cases are
 * abstentions with no reference answer, and scoring them would punish the
 * correct behaviour.
 */
class ChatbotJudgeService
{
    public const METRICS = ['faithfulness', 'answer_relevancy', 'answer_correctness'];

    public const MIN_SCORE = 1;

    public const MAX_SCORE = 4;

    public function __construct(protected GeminiService $gemini) {}

    /**
     * @param  string|null  $reference  Rendered reference answer, or null to skip correctness
     * @return array{id:string,model:string,faithfulness:int,answer_relevancy:int,answer_correctness:?int,reasoning:string}
     |         array{id:string,error:string}
     */
    public function scoreCase(string $caseId, string $query, string $reply, string $context, ?string $reference): array
    {
        if (trim($reply) === '') {
            return ['id' => $caseId, 'error' => 'empty chatbot answer'];
        }

        $prompt = str_replace(
            ['{QUERY}', '{REPLY}', '{CONTEXT}', '{REFERENCE}'],
            [$query, $reply, $context, $reference ?? '(none supplied — omit answer_correctness)'],
            $this->gemini->loadSystemPrompt('chatbot-judge-prompt.md')
        );

        $model = (string) (config('services.gemini.judge_model') ?? 'models/gemini-3.1-pro-preview');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $scores = $this->validate($this->gemini->generateContentWithModel(
                $model, 'You are a strict JSON evaluation grader.', $prompt, 0.0
            ), $reference !== null);
            if ($scores !== null) {
                return [
                    'id' => $caseId,
                    'model' => $model,
                    'faithfulness' => $scores['faithfulness'],
                    'answer_relevancy' => $scores['answer_relevancy'],
                    'answer_correctness' => $scores['answer_correctness'],
                    'reasoning' => $scores['reasoning'],
                ];
            }
        }

        return ['id' => $caseId, 'error' => 'judge returned invalid JSON twice'];
    }

    /**
     * @return array{faithfulness:int,answer_relevancy:int,answer_correctness:?int,reasoning:string}|null
     */
    protected function validate(?string $raw, bool $expectsCorrectness): ?array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode(trim($raw), true);
        if (! is_array($decoded)) {
            return null;
        }

        $scores = [];
        foreach (self::METRICS as $metric) {
            if ($metric === 'answer_correctness' && ! $expectsCorrectness) {
                if (array_key_exists($metric, $decoded)) {
                    return null;
                }
                $scores[$metric] = null;

                continue;
            }
            if (! isset($decoded[$metric]) || ! is_int($decoded[$metric]) || $decoded[$metric] < self::MIN_SCORE || $decoded[$metric] > self::MAX_SCORE) {
                return null;
            }
            $scores[$metric] = $decoded[$metric];
        }

        if (! isset($decoded['reasoning']) || ! is_string($decoded['reasoning']) || trim($decoded['reasoning']) === '') {
            return null;
        }

        $scores['reasoning'] = trim($decoded['reasoning']);

        return $scores;
    }

    /**
     * Each metric is averaged over the cases that were actually scored for it,
     * so a skipped correctness never drags the other two down.
     *
     * @param  array<int, array<string, mixed>>  $scored
     * @return array{n:int,faithfulness:float,answer_relevancy:float,answer_correctness:?float,correctness_n:int}
     */
    public function macroMeans(array $scored): array
    {
        $n = count($scored);
        if ($n === 0) {
            return ['n' => 0, 'faithfulness' => 0.0, 'answer_relevancy' => 0.0, 'answer_correctness' => null, 'correctness_n' => 0];
        }

        $means = [];
        $nByMetric = [];
        foreach (self::METRICS as $metric) {
            $values = array_values(array_filter(
                array_column($scored, $metric),
                fn ($v) => $v !== null
            ));
            $nByMetric[$metric] = count($values);
            $means[$metric] = $values === [] ? null : round(array_sum($values) / count($values), 4);
        }

        return [
            'n' => $n,
            'faithfulness' => $means['faithfulness'],
            'answer_relevancy' => $means['answer_relevancy'],
            'answer_correctness' => $means['answer_correctness'],
            'correctness_n' => $nByMetric['answer_correctness'],
        ];
    }
}
