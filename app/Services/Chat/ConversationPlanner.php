<?php

namespace App\Services\Chat;

use App\Models\ChatSession;
use App\Services\GeminiService;

final class ConversationPlanner
{
    public const ACTION = 'ACTION_REQUEST';

    public const UNSUPPORTED = 'UNSUPPORTED_INFORMATION';

    public const CLARIFY = 'CLARIFICATION';

    public const DRAFT = 'MESSAGE_DRAFT';

    public function __construct(
        private IntentRouter $router,
        private ConversationManager $conversation,
        private ChatCapabilities $capabilities,
        private GeminiService $gemini,
    ) {}

    /** @return array{handle: bool, tasks: list<array<string, mixed>>, source: string} */
    public function plan(string $message, ChatSession $session): array
    {
        $primary = $this->router->classify($message);
        if ($primary === IntentRouter::SUPPORT_AGENT) {
            return ['handle' => false, 'tasks' => [], 'source' => 'handoff'];
        }
        if ((preg_match('/^compare\b/iu', $message) && count($this->router->extractActivityNames($message)) >= 2 && ! str_contains($message, ';') && count($this->router->compoundSignals($message)) <= 1)
            || preg_match('/\bdistance\s+between\b.*\band\s+my\s+current\s+location[?.!]?$/iu', $message)
            || ($primary === IntentRouter::LEGAL_QUERY && preg_match('/\b(?:phone|street\s+address|owner|terms\s+and\s+conditions)\b/iu', $message) && ! preg_match('/(?:and|;)\s+(?:find|recommend|suggest|show)\b/iu', $message))) {
            return ['handle' => false, 'tasks' => [], 'source' => 'single_operation'];
        }
        $clauses = preg_split('/\s+(?:and(?!\s+conditions\b)|plus|also|as\s+well\s+as|then|tsaka|at\s+saka|saka|pati(?:\s+na)?)\s+|\s+at\s+(?=(?:(?:extreme|relaxing|cheap|budget)\s+)?(?:activities|tours?|hotels?|rooms?|transfers?)\b)|[,;](?=\s)|\s+[&+]\s+|[?!](?=\s|$)/iu', $message) ?: [$message];
        $clauses = array_values(array_filter(array_map('trim', $clauses)));
        $tasks = [];
        $lastAction = $session->metadata['conversation_v2']['last_action'] ?? null;
        if (preg_match('/^(?:just\s+do\s+it|do\s+it\s+anyway|proceed\s+anyway|gawin\s+mo\s+na)[.!]?$/iu', trim($message)) && is_array($lastAction)
            && isset($this->capabilities->operations()[$lastAction['operation'] ?? ''])) {
            return ['handle' => true, 'tasks' => [['id' => 'task-1', 'type' => self::ACTION, 'operation' => $lastAction['operation'], 'query' => $message, 'constraints' => [], 'budget_basis' => 'unknown']], 'source' => 'capability_continuation'];
        }
        $sharedClauses = [];
        $unresolved = [];
        $excluded = [];
        $excludedTerms = [];
        foreach ($clauses as $clause) {
            $signals = $this->signals($clause);
            if (preg_match('/\b(?:draft|write|prepare)\b.*\b(?:email|message|letter)\b/iu', $clause) && preg_match('/\b(?:hotel|resort|booking|trip|travel|check-in)\b/iu', $clause) && $this->router->outOfScopeTopic($clause) !== 'coding') {
                $tasks[] = $this->task(self::DRAFT, $clause);

                continue;
            }
            if (preg_match('/^(?:no|not|without|ayoko\s+ng|huwag|wag)\s+(?:any\s+)?(?:hotels?|rooms?|activities|tours?)\b/iu', $clause)) {
                $excluded = array_merge($excluded, $signals);

                continue;
            }
            if (preg_match('/^(?:no|not|without)\s+(snorkell?ing|diving|hiking|surfing|pool|wifi|wi-fi)\b/iu', $clause, $exclusion)) {
                $excludedTerms[] = mb_strtolower($exclusion[1]);

                continue;
            }
            if (preg_match('/\b(?:keep|retain|same|panatilihin)\b.*\b(?:hotel|room|activity)\b/iu', $clause)) {
                continue;
            }
            $operation = $this->capabilities->detect($clause);
            if ($operation !== null) {
                $tasks[] = $this->task(self::ACTION, $clause, ['operation' => $operation]);

                continue;
            }
            if (preg_match('/\b(?:restaurants?|dining|where\s+to\s+eat|kainan|cafes?|bus\s+schedule|flight\s+schedule)\b/iu', $clause)) {
                $tasks[] = $this->task(self::UNSUPPORTED, $clause);

                continue;
            }
            foreach ($signals as $signal) {
                $tasks[] = $this->task($signal, $clause);
            }
            if ($signals === []) {
                $sharedClauses[] = $clause;
                if (preg_match('/(?:₱|\bphp\b|\b\d+\s*(?:pax|persons?|nights?|days?|budget)\b)/iu', $clause)
                    && ! preg_match('/\b(?:find|recommend|suggest|show|compare)\b/iu', $clause)) {
                    continue;
                }
                if (preg_match('/\b(?:find|recommend|suggest|show|compare|viewpoints?|dining|hanap|pasyalan)\b/iu', $clause)
                    || (! preg_match('/^(?:\d|₱|php\b|under\b|below\b|budget\b|for\b|with\b|wifi\b|wi-fi\b|luxury\b|quiet\b|family\b|in\b|sa\b|january\b|february\b|march\b|april\b|may\b|june\b|july\b|august\b|september\b|october\b|november\b|december\b)/iu', $clause) && $this->router->extractDestinationName($clause) === null)) {
                    $unresolved[] = $clause;
                }
            }
        }
        $source = 'deterministic';
        if ($tasks === [] || count($clauses) === 1) {
            $unresolved = [];
        }
        if ($unresolved !== [] && config('services.chatbot.planner_enabled', true)) {
            $proposed = $this->gemini->planChatTasks($unresolved, $this->allowedIntents());
            foreach ($proposed as $proposal) {
                $tasks[] = $this->task($proposal['type'], $proposal['source']);
                $unresolved = array_values(array_diff($unresolved, [$proposal['source']]));
            }
            $source = $proposed === [] ? 'deterministic_fallback' : 'model_assisted';
        }
        foreach ($unresolved as $clause) {
            $tasks[] = $this->task(self::CLARIFY, $clause);
        }

        $groups = $this->conversation->resultGroups($session);
        $whole = $this->router->extractConstraints($message);
        $reference = (bool) preg_match('/\b(?:first|second|third|1st|2nd|3rd|that|this|same|selected|choices|cheaper|instead|actually|keep|iyon|yun|doon|mas\s+mura)\b/iu', $message);
        if ($tasks === [] && $groups !== [] && $reference) {
            $types = array_values(array_unique(array_column($groups, 'intent')));
            if (! empty($whole['destination_id'])) {
                foreach ($types as $type) {
                    $tasks[] = $this->task($type, $message);
                }
            } elseif (count($types) === 1) {
                $tasks[] = $this->task($types[0], $message);
            } else {
                $tasks[] = $this->task(self::CLARIFY, $message, ['clarification' => 'Do you mean the hotel, room, or activity from the previous results?']);
            }
        }
        $pending = $this->conversation->pendingTasks($session);
        if (count($tasks) === 1 && $groups !== [] && preg_match('/\b(?:available|availability|open)\b/iu', $message)
            && in_array($tasks[0]['type'], [IntentRouter::ROOM_SEARCH, IntentRouter::HOTEL_SEARCH], true)) {
            $tasks[0]['type'] = IntentRouter::AVAILABILITY_QUERY;
        }
        if (! empty($whole['check_in_date']) && ! empty($whole['check_out_date'])) {
            foreach ($pending as $task) {
                if (($task['outcome']['reason'] ?? null) === 'missing_dates') {
                    array_unshift($tasks, $this->task(IntentRouter::AVAILABILITY_QUERY, $message, ['inherited' => $task['constraints'] ?? []]));
                    break;
                }
            }
        }
        if ($tasks === [] && ! empty($whole['destination_id'])) {
            foreach ($pending as $task) {
                if (($task['outcome']['reason'] ?? null) === 'missing_destination') {
                    $tasks[] = $this->task($task['type'], ($task['query'] ?? '').' in '.$whole['destination_name'], ['inherited' => $task['constraints'] ?? []]);
                }
            }
        }
        if ($tasks === [] && $this->budgetBasis($message) !== 'unknown') {
            foreach ($pending as $task) {
                if (($task['outcome']['reason'] ?? null) === 'missing_budget_basis') {
                    $tasks[] = $this->task($task['type'], ($task['query'] ?? '').', '.$message, ['inherited' => $task['constraints'] ?? []]);
                    break;
                }
            }
        }
        $tasks = array_values(array_filter($tasks, fn (array $task): bool => ! in_array($task['type'], $excluded, true)));
        if ($tasks === [] && $excluded !== []) {
            $tasks[] = $this->task(self::CLARIFY, $message, ['clarification' => 'What would you like me to look for instead?']);
        }
        $destinations = [];
        $parsed = [];
        foreach ($tasks as $i => $task) {
            $parsed[$i] = $this->router->extractConstraints(count($tasks) === 1 ? $message : $task['query']);
            if (! empty($parsed[$i]['destination_id'])) {
                $destinations[$parsed[$i]['destination_id']] = $parsed[$i]['destination_name'];
            }
        }
        $shared = $this->router->extractConstraints(implode(', ', $sharedClauses));
        foreach (['pax', 'check_in_date', 'check_out_date', 'nights', 'days'] as $key) {
            $values = array_values(array_unique(array_filter(array_column($parsed, $key), fn (mixed $v): bool => $v !== null)));
            if (count($values) === 1 && empty($shared[$key])) {
                $shared[$key] = $values[0];
            }
        }
        if (count($destinations) === 1 && empty($shared['destination_id'])) {
            $shared['destination_id'] = array_key_first($destinations);
            $shared['destination_name'] = reset($destinations);
        }
        $jointBudget = (bool) preg_match('/\b(?:total|overall|for\s+both|combined|kabuuan)\b/iu', $message) && ! empty($whole['max_price']);
        foreach ($tasks as $i => &$task) {
            $task['id'] = 'task-'.($i + 1);
            $constraints = $parsed[$i];
            foreach (['destination_id', 'destination_name', 'pax', 'check_in_date', 'check_out_date', 'nights', 'days'] as $key) {
                $constraints[$key] ??= $shared[$key] ?? null;
            }
            $typeGroups = array_values(array_filter($groups, fn (array $group): bool => $group['intent'] === $task['type']
                || ($task['type'] === IntentRouter::AVAILABILITY_QUERY && in_array($group['intent'], [IntentRouter::ROOM_SEARCH, IntentRouter::AVAILABILITY_QUERY], true))));
            $task['reference'] = $reference;
            $isOrdinal = (bool) preg_match('/\b(?:first|second|third|1st|2nd|3rd)\b/iu', $task['query']);
            if (count($typeGroups) > 1 && $reference && empty($constraints['destination_id'])) {
                $task['clarification'] = 'Which destination or result group do you mean?';
            } elseif ($typeGroups !== [] && ($reference || empty($constraints['destination_id']))) {
                $group = $typeGroups[0];
                $sameDestination = empty($constraints['destination_id']) || (int) $constraints['destination_id'] === (int) ($group['constraints']['destination_id'] ?? 0);
                if ($sameDestination) {
                    foreach ($group['constraints'] ?? [] as $key => $value) {
                        if ($reference || in_array($key, ['destination_id', 'destination_name'], true)) {
                            $constraints[$key] ??= $value;
                        }
                    }
                    if ($isOrdinal || preg_match('/\b(?:that|this|selected|iyon|yun)\s+(?:hotel|room|activity|tour)\b/iu', $task['query'])) {
                        preg_match('/\b(first|second|third|1st|2nd|3rd)\b/iu', $task['query'], $ordinal);
                        $position = match (mb_strtolower($ordinal[1] ?? 'first')) {
                            'second', '2nd' => 1, 'third', '3rd' => 2, default => 0
                        };
                        $id = $group['result_ids'][$position] ?? null;
                        if ($id === null) {
                            $task['clarification'] = 'That position is not in the displayed results. Which option do you mean?';
                        } else {
                            $task['entity'] = ['type' => $group['type'], 'id' => $id];
                        }
                    }
                }
            }
            foreach ($task['inherited'] ?? [] as $key => $value) {
                $constraints[$key] ??= $value;
            }
            if ($task['type'] === IntentRouter::ITINERARY_QUERY && $reference) {
                foreach ($groups as $group) {
                    if (! in_array($group['type'], ['hotel', 'room', 'activity'], true)) {
                        continue;
                    }
                    if (! empty($constraints['destination_id']) && (int) $constraints['destination_id'] !== (int) ($group['constraints']['destination_id'] ?? 0)) {
                        continue;
                    }
                    if (count($group['result_ids']) !== 1) {
                        $task['clarification'] = 'Which hotel or room and activity should I include? Please choose specific options from the results.';

                        continue;
                    }
                    foreach (['destination_id', 'destination_name', 'pax', 'nights', 'check_in_date', 'check_out_date'] as $key) {
                        $constraints[$key] ??= $group['constraints'][$key] ?? null;
                    }
                    if ($group['type'] === 'activity') {
                        $constraints['selected_activity_ids'][] = (int) $group['result_ids'][0];
                    } else {
                        $constraints[$group['type'].'_id'] = (int) $group['result_ids'][0];
                        $constraints['preserve_selected_stay'] = true;
                    }
                }
            }
            if ($jointBudget) {
                $task['joint_budget'] = ['amount' => $whole['max_price'], 'basis' => 'trip_total'];
                $constraints['max_price'] = $task['type'] === IntentRouter::ITINERARY_QUERY ? $whole['max_price'] : null;
            }
            $task['budget_basis'] = $jointBudget ? 'trip_total' : $this->budgetBasis($task['query']);
            $constraints['excluded_terms'] = $excludedTerms;
            $task['constraints'] = $constraints;
            $task['explicit_constraints'] = array_keys(array_filter($parsed[$i], static fn (mixed $v): bool => $v !== null && $v !== []));
            if (empty($constraints['destination_id']) && count($destinations) > 1) {
                $task['clarification'] ??= 'Which destination should I use for this part of your request?';
            }
        }
        unset($task);
        $tasks = array_values(array_unique($tasks, SORT_REGULAR));
        $special = array_filter($tasks, fn (array $task): bool => in_array($task['type'], [self::ACTION, self::UNSUPPORTED, self::CLARIFY, self::DRAFT], true) || isset($task['clarification']));

        return ['handle' => count($tasks) > 1 || $special !== [] || $excluded !== [] || $excludedTerms !== [] || ($groups !== [] && $reference) || ($pending !== [] && $tasks !== []), 'tasks' => $tasks, 'source' => $source];
    }

    /** @return list<string> */
    private function signals(string $query): array
    {
        $signals = $this->router->compoundSignals($query);
        if (in_array(IntentRouter::ITINERARY_QUERY, $signals, true)) {
            return array_values(array_diff($signals, [IntentRouter::ROOM_SEARCH, IntentRouter::HOTEL_SEARCH, IntentRouter::ACTIVITY_SEARCH]));
        }
        if (in_array(IntentRouter::AVAILABILITY_QUERY, $signals, true)) {
            return array_values(array_diff($signals, [IntentRouter::ROOM_SEARCH, IntentRouter::HOTEL_SEARCH]));
        }
        if (preg_match('/\b(?:hotel|resort|room)\b.*\bwith\b/iu', $query) && ! preg_match('/\b(?:activities|things\s+to\s+do)\b/iu', $query)) {
            return array_values(array_diff($signals, [IntentRouter::ACTIVITY_SEARCH]));
        }

        return $signals;
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function task(string $type, string $query, array $extra = []): array
    {
        return ['type' => $type, 'query' => mb_substr($query, 0, 1000), ...$extra];
    }

    /** @return list<string> */
    private function allowedIntents(): array
    {
        return [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ACTIVITY_SEARCH, IntentRouter::PACKAGE_SEARCH, IntentRouter::ADDON_SEARCH, IntentRouter::WEATHER_QUERY, IntentRouter::MAP_QUERY, IntentRouter::ITINERARY_QUERY];
    }

    private function budgetBasis(string $query): string
    {
        return match (true) {
            (bool) preg_match('/(?:per\s*night|\/night|bawat\s*gabi)/iu', $query) => 'per_night',
            (bool) preg_match('/(?:per\s*(?:person|pax|head)|\/person|\/pax)/iu', $query) => 'per_person',
            (bool) preg_match('/\bper\s*activity\b/iu', $query) => 'per_activity',
            default => 'unknown',
        };
    }
}
