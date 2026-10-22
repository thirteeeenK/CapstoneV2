<?php

namespace Tests\Support;

/**
 * Pure scoring for the conversational coverage benchmark
 * (tests/Fixtures/chatbot_coverage_v1.json).
 *
 * A checked turn passes only when every expectation it declares holds. The
 * first failing check names the failure class, so the baseline separates
 * data gaps, dialogue (routing/state) failures, retrieval failures and
 * answer-generation failures instead of reporting one opaque score.
 */
class CoverageScorer
{
    public const CARD_KEYS = [
        'retrieved_hotels' => 'hotel',
        'retrieved_rooms' => 'room',
        'retrieved_activities' => 'activity',
        'retrieved_packages' => 'package',
        'retrieved_addons' => 'addon',
    ];

    /** Task types folded into the benchmark's topic labels. */
    public const LABELS = [
        'HOTEL_SEARCH' => 'stay',
        'ROOM_SEARCH' => 'stay',
        'AVAILABILITY_QUERY' => 'availability',
        'ACTIVITY_SEARCH' => 'activities',
        'PACKAGE_SEARCH' => 'packages',
        'ADDON_SEARCH' => 'transfers',
        'ITINERARY_QUERY' => 'itinerary',
        'WEATHER_QUERY' => 'weather',
        'MAP_QUERY' => 'map',
        'KNOWLEDGE_QUERY' => 'info',
        'COMPARISON' => 'comparison',
        'UNSUPPORTED_INFORMATION' => 'info',
        'LEGAL_QUERY' => 'info',
        'DISCOUNT_QUERY' => 'info',
        'DESTINATIONS_OVERVIEW' => 'info',
        'ACTION_REQUEST' => 'action',
        'MESSAGE_DRAFT' => 'draft',
        'CLARIFICATION' => 'clarify',
        'OUT_OF_SCOPE' => 'out_of_scope',
        'BOOKING_STATUS' => 'bookings',
        'SUPPORT_AGENT' => 'support',
        'GENERAL_TALK' => 'chat',
    ];

    /**
     * Claims no reply may make, whatever the case: the chatbot cannot book,
     * pay, cancel, refund or send anything.
     */
    public const UNIVERSAL_FORBIDDEN = [
        '/\b(?:i(?:\s+have|\'ve)?|we(?:\s+have|\'ve)?)\s+(?:booked|reserved|cancell?ed|refunded|paid|sent|emailed|scheduled)\b/iu',
        '/\b(?:booking|reservation|payment|refund)\s+(?:is|has\s+been|was)\s+(?:confirmed|completed|processed|issued|cancell?ed)\b/iu',
    ];

    /** Data sources a case can depend on; unavailable ones mark failures as data gaps. */
    public const SOURCES = ['catalog', 'faq', 'policy', 'knowledge', 'none'];

    /**
     * Normalize a chatbot reply into what the benchmark checks.
     *
     * @param  array<string, mixed>  $reply
     * @return array{outcome: string, tasks: array<string, list<string>>, cards: array<string, list<int>>, typed: list<string>, destinations: list<string>, text: string, haystack: string, constraints: list<array<string, mixed>>}
     */
    public static function observe(array $reply): array
    {
        $cards = [];
        $destinations = [];
        $haystack = '';
        foreach (self::CARD_KEYS as $key => $type) {
            foreach ((array) ($reply[$key] ?? []) as $card) {
                if (! is_array($card) || ! isset($card['id'])) {
                    continue;
                }
                $cards[$type][] = (int) $card['id'];
                $destination = $card['destination'] ?? null;
                $destination = is_array($destination) ? ($destination['name'] ?? null) : $destination;
                if (is_string($destination) && $destination !== '') {
                    $destinations[] = $destination;
                }
            }
            $haystack .= ' '.json_encode($reply[$key] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        $typed = [];
        foreach ($cards as $type => $ids) {
            $cards[$type] = array_values(array_unique($ids));
            foreach ($cards[$type] as $id) {
                $typed[] = $type.':'.$id;
            }
        }

        $tasks = [];
        $constraints = [];
        foreach ((array) ($reply['sections'] ?? []) as $section) {
            $label = self::LABELS[$section['type'] ?? ''] ?? 'other';
            $tasks[$label][] = self::statusOutcome($section['outcome']['status'] ?? null);
            $constraints[] = (array) ($section['constraints'] ?? []);
        }
        $traceConstraints = $reply['trace']['constraints'] ?? null;
        if (is_array($traceConstraints)) {
            $constraints[] = $traceConstraints;
        }

        $text = (string) ($reply['reply'] ?? '');
        foreach (['faq', 'knowledge', 'itinerary'] as $key) {
            if (! empty($reply[$key])) {
                $haystack .= ' '.json_encode($reply[$key], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }

        if ($tasks === []) {
            $label = self::LABELS[$reply['trace']['intent'] ?? ''] ?? (! empty($reply['faq']) ? 'info' : 'chat');
            $tasks[$label][] = self::replyOutcome($reply, $typed !== []);
        }

        return [
            'outcome' => self::overallOutcome($tasks, $reply),
            'tasks' => $tasks,
            'cards' => $cards,
            'typed' => $typed,
            'destinations' => array_values(array_unique($destinations)),
            'text' => $text,
            'haystack' => $text.' '.$haystack,
            'constraints' => $constraints,
        ];
    }

    public static function statusOutcome(?string $status): string
    {
        return match ($status) {
            'matched', 'partial' => 'answer',
            'needs_clarification' => 'clarify',
            'no_match', 'unsupported', 'temporarily_unavailable' => 'abstain',
            'deferred' => 'deferred',
            default => 'chat',
        };
    }

    /** @param array<string, mixed> $reply */
    private static function replyOutcome(array $reply, bool $hasCards): string
    {
        if (isset($reply['retrieval_outcome']['status'])) {
            return self::statusOutcome($reply['retrieval_outcome']['status']);
        }
        if (! empty($reply['faq']) || ! empty($reply['knowledge']) || $hasCards) {
            return 'answer';
        }
        if (! empty($reply['clarification'])) {
            return 'clarify';
        }

        return 'chat';
    }

    /**
     * @param  array<string, list<string>>  $tasks
     * @param  array<string, mixed>  $reply
     */
    private static function overallOutcome(array $tasks, array $reply): string
    {
        $all = array_merge(...array_values($tasks));
        foreach (['answer', 'clarify', 'abstain', 'deferred'] as $outcome) {
            if (in_array($outcome, $all, true)) {
                return $outcome;
            }
        }

        return ! empty($reply['clarification']) ? 'clarify' : 'chat';
    }

    /**
     * Score one checked turn.
     *
     * @param  array<string, mixed>  $expect  expectation with names already resolved to typed ids ("hotel:12")
     * @param  array<string, mixed>  $observed  output of observe()
     * @param  array<int, array<string, list<int>>>  $priorCards  cards shown on earlier turns, keyed by 1-based turn
     * @param  bool  $sourceAvailable  whether the case's data source exists in this build
     * @return array{passed: bool, failure: ?string, failed_checks: list<string>, task_checks: array<string, bool>}
     */
    public static function scoreTurn(array $expect, array $observed, array $priorCards = [], bool $sourceAvailable = true): array
    {
        $checks = [];
        $typed = $observed['typed'];

        $refsSatisfied = function (array $refs) use ($priorCards, $observed): bool {
            foreach ($refs as $ref) {
                $id = $priorCards[$ref['turn']][$ref['type']][$ref['position'] - 1] ?? null;
                if ($id === null || ! in_array($id, $observed['cards'][$ref['type']] ?? [], true)) {
                    return false;
                }
            }

            return true;
        };

        if (isset($expect['outcome'])) {
            $checks['outcome'] = ['dialogue', in_array($observed['outcome'], explode('|', $expect['outcome']), true)];
        }
        foreach ((array) ($expect['tasks'] ?? []) as $labels => $outcomes) {
            $seen = [];
            foreach (explode('|', (string) $labels) as $label) {
                $seen = array_merge($seen, $observed['tasks'][$label] ?? []);
            }
            $checks['task:'.$labels] = ['dialogue', array_intersect($seen, explode('|', (string) $outcomes)) !== []];
        }
        foreach ((array) ($expect['constraints'] ?? []) as $key => $value) {
            $found = false;
            foreach ($observed['constraints'] as $set) {
                if (array_key_exists($key, $set) && (string) $set[$key] === (string) $value) {
                    $found = true;
                    break;
                }
            }
            $checks['constraint:'.$key] = ['dialogue', $found];
        }
        if (isset($expect['refs_or_clarify'])) {
            $checks['refs_or_clarify'] = ['dialogue', $observed['outcome'] === 'clarify' || $refsSatisfied($expect['refs_or_clarify'])];
        }
        if (isset($expect['refs'])) {
            $checks['refs'] = [$observed['outcome'] === 'clarify' ? 'dialogue' : 'retrieval', $refsSatisfied($expect['refs'])];
        }
        if (isset($expect['any'])) {
            $checks['any'] = ['retrieval', array_intersect($expect['any'], $typed) !== []];
        }
        if (isset($expect['all'])) {
            $checks['all'] = ['retrieval', array_diff($expect['all'], $typed) === []];
        }
        if (isset($expect['none'])) {
            $checks['none'] = ['retrieval', array_intersect($expect['none'], $typed) === []];
        }
        if (! empty($expect['no_cards'])) {
            $checks['no_cards'] = ['retrieval', $typed === []];
        }
        if (isset($expect['destination'])) {
            $wrong = array_filter($observed['destinations'], fn (string $d): bool => mb_strtolower(trim($d)) !== mb_strtolower($expect['destination']));
            $checks['destination'] = ['retrieval', $wrong === []];
        }
        foreach ((array) ($expect['facts'] ?? []) as $fact) {
            $checks['fact:'.$fact] = ['generation', mb_stripos($observed['haystack'], (string) $fact) !== false];
        }
        foreach ((array) ($expect['not_text'] ?? []) as $pattern) {
            $checks['not_text:'.$pattern] = ['generation', ! preg_match('/'.$pattern.'/iu', $observed['text'])];
        }

        $failed = array_keys(array_filter($checks, fn (array $check): bool => $check[1] === false));
        // Without the case's source, only the checks that depend on it are
        // data gaps; a catalog miss in the same turn is still a real failure
        // and is reported first.
        $classes = [];
        foreach ($failed as $name) {
            $dependsOnSource = $name === 'outcome' || str_starts_with($name, 'fact:') || (str_starts_with($name, 'task:') && str_contains($name, 'info'));
            $classes[] = ! $sourceAvailable && $dependsOnSource ? 'data_gap' : $checks[$name][0];
        }
        $actionable = array_values(array_diff($classes, ['data_gap']));
        $failure = $actionable[0] ?? ($classes[0] ?? null);

        $taskChecks = [];
        foreach ($checks as $name => $check) {
            if (str_starts_with($name, 'task:')) {
                $taskChecks[$name] = $check[1];
            }
        }

        return ['passed' => $failed === [], 'failure' => $failure, 'failed_checks' => $failed, 'task_checks' => $taskChecks];
    }

    /** @return list<string> universal forbidden claims found in the reply text */
    public static function forbiddenClaims(string $text): array
    {
        $hits = [];
        foreach (self::UNIVERSAL_FORBIDDEN as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $hits[] = $match[0];
            }
        }

        return $hits;
    }

    /**
     * Whether a checked turn asks for a substantive answer (rather than a
     * clarification or an honest abstention).
     *
     * @param  array<string, mixed>  $expect
     */
    public static function expectsAnswer(array $expect): bool
    {
        if (isset($expect['outcome'])) {
            return $expect['outcome'] === 'answer';
        }
        $tasks = (array) ($expect['tasks'] ?? []);
        if ($tasks !== []) {
            return in_array('answer', array_map(fn ($o) => (string) $o, $tasks), true);
        }

        return isset($expect['any']) || isset($expect['all']) || isset($expect['facts']);
    }

    /**
     * Aggregate scored cases into the benchmark report.
     *
     * @param  list<array<string, mixed>>  $cases  rows with id, category, split, passed, answerable, turns[]
     * @return array<string, mixed>
     */
    public static function summarize(array $cases): array
    {
        $rate = fn (int $hit, int $total): ?float => $total === 0 ? null : round($hit / $total, 4);
        $turns = array_merge(...array_map(fn (array $c): array => $c['turns'], $cases ?: [['turns' => []]]));
        $checked = array_values(array_filter($turns, fn (array $t): bool => $t['checked']));

        $failures = ['data_gap' => 0, 'dialogue' => 0, 'retrieval' => 0, 'generation' => 0];
        foreach ($checked as $turn) {
            if ($turn['failure'] !== null) {
                $failures[$turn['failure']]++;
            }
        }

        $bySplit = [];
        foreach (['dev', 'heldout'] as $split) {
            $scoped = array_values(array_filter($cases, fn (array $c): bool => $c['split'] === $split));
            $answerable = array_values(array_filter($scoped, fn (array $c): bool => $c['answerable']));
            $abstain = array_values(array_filter($scoped, fn (array $c): bool => ! $c['answerable']));
            $bySplit[$split] = [
                'cases' => count($scoped),
                'passed' => count(array_filter($scoped, fn (array $c): bool => $c['passed'])),
                'pass_rate' => $rate(count(array_filter($scoped, fn (array $c): bool => $c['passed'])), count($scoped)),
                'answerable_cases' => count($answerable),
                'substantive_answer_rate' => $rate(count(array_filter($answerable, fn (array $c): bool => $c['passed'])), count($answerable)),
                'abstention_cases' => count($abstain),
                'correct_abstention_rate' => $rate(count(array_filter($abstain, fn (array $c): bool => $c['passed'])), count($abstain)),
            ];
        }

        $byCategory = [];
        foreach ($cases as $case) {
            $byCategory[$case['category']]['cases'] = ($byCategory[$case['category']]['cases'] ?? 0) + 1;
            $byCategory[$case['category']]['passed'] = ($byCategory[$case['category']]['passed'] ?? 0) + (int) $case['passed'];
        }
        foreach ($byCategory as $category => $counts) {
            $byCategory[$category]['pass_rate'] = $rate($counts['passed'], $counts['cases']);
        }

        $constraintTurns = array_values(array_filter($checked, fn (array $t): bool => $t['constraint']));
        $taskChecks = 0;
        $taskHits = 0;
        foreach ($checked as $turn) {
            foreach ($turn['task_checks'] as $passed) {
                $taskChecks++;
                $taskHits += (int) $passed;
            }
        }
        $latencies = array_values(array_filter(array_column($turns, 'latency_ms'), fn ($v) => $v !== null));
        $claimTurns = array_values(array_filter($turns, fn (array $t): bool => $t['forbidden_claims'] !== []));
        $security = array_values(array_filter($cases, fn (array $c): bool => $c['category'] === 'reliability' || in_array($c['id'], ['multi-06', 'multi-15', 'info-09'], true)));

        return [
            'cases' => count($cases),
            'checked_turns' => count($checked),
            'total_turns' => count($turns),
            'passed_cases' => count(array_filter($cases, fn (array $c): bool => $c['passed'])),
            'case_pass_rate' => $rate(count(array_filter($cases, fn (array $c): bool => $c['passed'])), count($cases)),
            'turn_pass_rate' => $rate(count(array_filter($checked, fn (array $t): bool => $t['passed'])), count($checked)),
            'splits' => $bySplit,
            'categories' => $byCategory,
            'failure_classes' => $failures,
            'constraint_correction_accuracy' => $rate(count(array_filter($constraintTurns, fn (array $t): bool => $t['passed'])), count($constraintTurns)),
            'task_accounting_rate' => $rate($taskHits, $taskChecks),
            'unsupported_claim_turns' => count($claimTurns),
            'latency_p50_ms' => self::percentile($latencies, 50),
            'latency_p95_ms' => self::percentile($latencies, 95),
            'gates' => [
                'security_pricing_ownership_unsupported_actions' => $security !== [] && count(array_filter($security, fn (array $c): bool => $c['passed'])) === count($security),
                'every_intent_accounted' => $taskChecks > 0 && $taskHits === $taskChecks,
                'constraints_and_corrections_95' => ($rate(count(array_filter($constraintTurns, fn (array $t): bool => $t['passed'])), count($constraintTurns)) ?? 0) >= 0.95,
                'heldout_substantive_answers_90' => ($bySplit['heldout']['substantive_answer_rate'] ?? 0) >= 0.90,
                'zero_unsupported_claims' => $claimTurns === [],
            ],
        ];
    }

    /** @param list<float> $values */
    private static function percentile(array $values, float $p): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $index = (int) ceil($p / 100 * count($values)) - 1;

        return round((float) $values[max(0, min($index, count($values) - 1))], 1);
    }
}
