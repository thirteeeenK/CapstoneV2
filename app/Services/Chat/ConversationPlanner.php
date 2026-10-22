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

    public const KNOWLEDGE = 'KNOWLEDGE_QUERY';

    public const COMPARE = 'COMPARISON';

    /** Positional references in English and Filipino ("yung pangalawa"). */
    public const ORDINALS = 'first|second|third|fourth|fifth|1st|2nd|3rd|4th|5th|una|unang|pang-?una|pangalawa|ikalawa|pangatlo|ikatlo|pang-?apat|ikaapat|panlima|ikalima';

    /** Zero-based result position for an ordinal word. */
    public static function ordinalPosition(string $ordinal): int
    {
        return match (true) {
            (bool) preg_match('/^(?:second|2nd|pangalawa|ikalawa)$/iu', $ordinal) => 1,
            (bool) preg_match('/^(?:third|3rd|pangatlo|ikatlo)$/iu', $ordinal) => 2,
            (bool) preg_match('/^(?:fourth|4th|pang-?apat|ikaapat)$/iu', $ordinal) => 3,
            (bool) preg_match('/^(?:fifth|5th|panlima|ikalima)$/iu', $ordinal) => 4,
            default => 0,
        };
    }

    public function __construct(
        private IntentRouter $router,
        private ConversationManager $conversation,
        private ChatCapabilities $capabilities,
        private GeminiService $gemini,
    ) {}

    /** @return array{handle: bool, tasks: list<array<string, mixed>>, source: string} */
    public function plan(string $message, ChatSession $session): array
    {
        // Planned tasks echo their clause text back in the response; markup
        // never carries travel meaning, so it is removed before planning.
        $message = trim((string) preg_replace('/\s+/u', ' ', strip_tags($message)));
        $primary = $this->router->classify($message);
        if ($primary === IntentRouter::SUPPORT_AGENT) {
            return ['handle' => false, 'tasks' => [], 'source' => 'handoff'];
        }
        $comparison = $this->comparisonTask($message, $session);
        if ((preg_match('/^compare\b/iu', $message) && count($this->router->extractActivityNames($message)) >= 2 && ! str_contains($message, ';') && count($this->router->compoundSignals($message)) <= 1)
            || preg_match('/\bdistance\s+between\b.*\band\s+my\s+current\s+location[?.!]?$/iu', $message)
            || ($primary === IntentRouter::LEGAL_QUERY && preg_match('/\b(?:phone|street\s+address|owner|terms\s+and\s+conditions)\b/iu', $message) && ! preg_match('/(?:and|;)\s+(?:find|recommend|suggest|show)\b/iu', $message))) {
            return ['handle' => false, 'tasks' => [], 'source' => 'single_operation'];
        }
        $protectedNames = [];
        $clauseMessage = $message;
        if (in_array($comparison['comparison']['type'] ?? null, ['room', 'hotel'], true)) {
            foreach ($this->router->mentionedCatalogItems($message, $comparison['comparison']['type']) as $item) {
                $placeholder = '__comparison_'.hash('sha256', $message.$item['id']).'__';
                $protectedNames[$placeholder] = $item['name'];
                $clauseMessage = (string) preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($item['name'], '/').'(?![\p{L}\p{N}])/iu', $placeholder, $clauseMessage);
            }
        }
        if ($comparison !== null) {
            $join = '__comparison_join_'.hash('sha256', $message).'__';
            $protectedNames[$join] = ' and ';
            $clauseMessage = (string) preg_replace('/(?:\s+(?:and|plus|at|[&+])\s+|,\s*)(?=(?:the\s+)?(?:__comparison_[a-f0-9]+__|'.self::ORDINALS.')\b)/iu', $join, $clauseMessage);
        }
        $separator = '/\s+(?:and(?!\s+conditions\b)|plus|also|as\s+well\s+as|then|tsaka|at\s+saka|saka|pati(?:\s+na)?)\s+|\s+at\s+(?=(?:(?:extreme|relaxing|cheap|budget)\s+)?(?:activities|tours?|hotels?|rooms?|transfers?)\b)|[,;](?=\s)|\s+[&+]\s+|[?!](?=\s|$)/iu';
        $clauses = preg_split($separator, $clauseMessage) ?: [$clauseMessage];
        $clauses = array_map(fn (string $clause): string => strtr($clause, $protectedNames), $clauses);
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
        $excludedNames = [];
        foreach ($clauses as $clause) {
            // Negation is resolved before positive extraction so a ruled-out
            // feature or catalog item can never become a positive task.
            $exclusions = $this->router->extractExclusions($clause);
            $signals = $this->signals($clause);
            if (($clauseComparison = $this->comparisonTask($clause, $session)) !== null) {
                $clauseComparison['inherited'] = $clauseComparison['constraints'];
                $tasks[] = $clauseComparison;

                continue;
            }
            if (preg_match('/\b(?:draft|write|prepare)\b.*\b(?:email|message|letter)\b/iu', $clause) && preg_match('/\b(?:hotel|resort|booking|trip|travel|check-in)\b/iu', $clause) && $this->router->outOfScopeTopic($clause) !== 'coding') {
                $tasks[] = $this->task(self::DRAFT, $clause);

                continue;
            }
            if (preg_match('/^(?:no|not|without|ayoko\s+ng|huwag|wag)\s+(?:any\s+)?(?:hotels?|rooms?|activities|tours?)\b/iu', $clause)) {
                $excluded = array_merge($excluded, $signals);

                continue;
            }
            // A clause that only rules something out applies to the whole
            // turn. This is decided before positive signals are read: the
            // words inside "no snorkeling" look like an activity request, and
            // must not become one.
            $exclusionOnly = ($exclusions['terms'] !== [] || $exclusions['names'] !== [] || $exclusions['unclear'] !== [])
                && preg_match('/^(?:no|not|without|wala(?:ng)?|ayaw|ayoko|huwag|wag|skip|exclude|excluding|except|avoid|minus)\b/iu', trim($clause))
                && ! preg_match('/\b(?:show|find|recommend|suggest|give|book|instead|but|pero)\b/iu', $clause);
            if ($exclusionOnly && ($exclusions['terms'] !== [] || $exclusions['names'] !== [])) {
                $excludedTerms = array_merge($excludedTerms, $exclusions['terms']);
                $excludedNames = array_merge($excludedNames, $exclusions['names']);

                continue;
            }
            // An exclusion the catalog vocabulary cannot resolve is asked
            // about rather than silently dropped or treated as a feature.
            if ($exclusionOnly) {
                $tasks[] = $this->task(self::CLARIFY, $clause, [
                    'clarification' => 'I could not match “'.$exclusions['unclear'][0].'” to anything in the catalog. Which option should I leave out?',
                ]);

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
            // Timetables have no verified source at all; narrative travel
            // information (dining, local transport, access, fees...) is
            // answered from reviewed knowledge articles instead.
            if (preg_match('/\b(?:bus|flight|ferry|boat|van)\s+(?:schedules?|timetables?)\b/iu', $clause)) {
                $tasks[] = $this->task(self::UNSUPPORTED, $clause);

                continue;
            }
            if (($topic = $this->knowledgeTopic($clause, $signals)) !== null) {
                $tasks[] = $this->task(self::KNOWLEDGE, $clause, ['topic' => $topic]);

                continue;
            }
            foreach ($signals as $signal) {
                $tasks[] = $this->task($signal, $clause, array_filter([
                    'clause_excluded_terms' => $exclusions['terms'],
                    'clause_excluded_names' => $exclusions['names'],
                ]));
            }
            if ($signals === []) {
                $sharedClauses[] = $clause;
                if ($this->constraintOnlyClause($clause)) {
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
        $unknownDestination = empty($whole['destination_id']) && empty($whole['hotel_id']) && empty($whole['room_id']) && (bool) preg_match('/\b(?:in|near|sa)\s+[\p{L}]/iu', $message);
        $reference = (bool) preg_match('/\b(?:'.self::ORDINALS.'|that|this|same|selected|choices|cheaper|instead|actually|keep|iyon|yun|doon|mas\s+mura|how\s+about|what\s+about)\b/iu', $message);
        // A bare correction of the last search ("for 2 adults and 2 kids",
        // "no water activities") refines it rather than starting over, unless
        // a guided question is waiting for exactly that answer.
        $openQuestion = ! empty($session->metadata['conversation_v2']['dialogue']['active_question']);
        $refinement = ! $openQuestion && $this->conversation->pendingTasks($session) === []
            && ($excludedTerms !== [] || $excludedNames !== [] || (! empty($whole['pax']) && $this->constraintOnlyClause($message)));
        if ($tasks === [] && $groups !== [] && ($reference || $refinement)) {
            $reference = true;
            $types = array_values(array_unique(array_column($groups, 'intent')));
            $changesDestination = ! empty($whole['destination_id']);
            if ($changesDestination) {
                foreach ($types as $type) {
                    $tasks[] = $this->task($type, $message);
                }
            } elseif (count($types) === 1) {
                $tasks[] = $this->refinementTask($types[0], $message, $groups);
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
        if (($whole['include_accommodation'] ?? null) === false && in_array(IntentRouter::ITINERARY_QUERY, array_column($tasks, 'type'), true)) {
            $tasks = array_values(array_filter($tasks, fn (array $task): bool => ! in_array($task['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH], true)));
        }
        if ($tasks === [] && $excluded !== []) {
            $tasks[] = $this->task(self::CLARIFY, $message, ['clarification' => 'What would you like me to look for instead?']);
        }
        $destinations = [];
        $parsed = [];
        foreach ($tasks as $i => $task) {
            $parsed[$i] = $this->router->extractConstraints(count($tasks) === 1 ? $message : $task['query']);
            // A refinement re-runs the earlier request: its own constraints
            // fill whatever the correction did not restate.
            if (! empty($task['refinement']) && $task['query'] !== $message) {
                $earlier = $this->router->extractConstraints($task['query']);
                foreach ($earlier as $key => $value) {
                    if (($parsed[$i][$key] ?? null) === null || ($parsed[$i][$key] ?? null) === []) {
                        $parsed[$i][$key] = $value;
                    }
                }
            }
            if (! empty($parsed[$i]['destination_id'])) {
                $destinations[$parsed[$i]['destination_id']] = $parsed[$i]['destination_name'];
            }
        }
        $shared = $this->router->extractConstraints(implode(', ', $sharedClauses));
        if (count($destinations) <= 1 && array_key_exists('include_accommodation', $whole)) {
            $shared['include_accommodation'] = $whole['include_accommodation'];
        }
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
        if (! $unknownDestination && $destinations === [] && empty($shared['destination_id'])) {
            $previousDestinations = [];
            foreach ($groups as $group) {
                if (! empty($group['constraints']['destination_id'])) {
                    $previousDestinations[$group['constraints']['destination_id']] = $group['constraints']['destination_name'] ?? null;
                }
            }
            if (count($previousDestinations) === 1) {
                $shared['destination_id'] = array_key_first($previousDestinations);
                $shared['destination_name'] = reset($previousDestinations);
            }
        }
        // A budget is only "joint" when there is more than one part to split it
        // between; with a single request the whole amount is simply that
        // request's budget and there is nothing to allocate.
        $spendableTasks = count(array_filter($tasks, fn (array $task): bool => in_array($task['type'], [
            IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ACTIVITY_SEARCH,
            IntentRouter::PACKAGE_SEARCH, IntentRouter::ADDON_SEARCH, IntentRouter::ITINERARY_QUERY,
            IntentRouter::AVAILABILITY_QUERY, self::COMPARE,
        ], true)));
        // "Same dates", "keep the same budget": the accepted trip facts fill
        // what the message points back to, even after the recent window.
        $facts = $this->conversation->tripFacts($session);
        $sameFacts = [];
        if (preg_match('/\bsame\s+(?:dates?|schedule)\b|\bpareho(?:ng)?\s+(?:petsa|araw)\b/iu', $message)) {
            array_push($sameFacts, 'check_in_date', 'check_out_date', 'nights');
        }
        if (preg_match('/\b(?:same|keep\s+the(?:\s+same)?)\s+budget\b/iu', $message)) {
            array_push($sameFacts, 'max_price', 'budget_mode');
        }
        if (preg_match('/\bsame\s+(?:group|pax|number\s+of\s+(?:people|guests|travell?ers))\b/iu', $message)) {
            $sameFacts[] = 'pax';
        }
        $jointBudget = (bool) preg_match('/\b(?:total|overall|for\s+both|combined|kabuuan)\b/iu', $message)
            && ! empty($whole['max_price'])
            && $spendableTasks > 1;
        foreach ($tasks as $i => &$task) {
            $task['id'] = 'task-'.($i + 1);
            $constraints = $parsed[$i];
            $localUnknownDestination = empty($constraints['destination_id']) && empty($constraints['hotel_id']) && empty($constraints['room_id']) && (bool) preg_match('/\b(?:in|near|sa)\s+[\p{L}]/iu', $task['query']);
            $task['explicit_unknown_destination'] = $unknownDestination || $localUnknownDestination;
            $task['destination_query'] = $unknownDestination ? $message : $task['query'];
            foreach (['destination_id', 'destination_name', 'pax', 'check_in_date', 'check_out_date', 'nights', 'days', 'include_accommodation'] as $key) {
                if ($task['explicit_unknown_destination'] && in_array($key, ['destination_id', 'destination_name'], true)) {
                    continue;
                }
                $constraints[$key] ??= $shared[$key] ?? null;
            }
            if (! empty($shared['max_price']) && empty($constraints['max_price'])) {
                $constraints['max_price'] = $shared['max_price'];
                $constraints['budget_mode'] = $shared['budget_mode'];
                $task['budget_scope'] = 'shared';
            }
            $typeGroups = array_values(array_filter($groups, fn (array $group): bool => $group['intent'] === $task['type']
                || ($task['type'] === IntentRouter::AVAILABILITY_QUERY && in_array($group['intent'], [IntentRouter::ROOM_SEARCH, IntentRouter::AVAILABILITY_QUERY], true))));
            if ($task['explicit_unknown_destination']) {
                $typeGroups = [];
            }
            $task['reference'] = $reference;
            $isOrdinal = (bool) preg_match('/\b(?:'.self::ORDINALS.')\b/iu', $task['query']);
            if (count($typeGroups) > 1 && empty($constraints['destination_id'])) {
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
                        preg_match('/\b('.self::ORDINALS.')\b/iu', $task['query'], $ordinal);
                        $position = self::ordinalPosition($ordinal[1] ?? 'first');
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
            foreach ($sameFacts as $key) {
                $constraints[$key] ??= $facts[$key] ?? null;
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
            $task['budget_basis'] = $jointBudget ? 'trip_total' : $this->budgetBasis(count($tasks) === 1 ? $message : $task['query']);
            // A budget stated in its own clause ("..., total budget 4000")
            // carries its basis with it.
            if ($task['budget_basis'] === 'unknown' && ($task['budget_scope'] ?? null) === 'shared') {
                $task['budget_basis'] = $this->budgetBasis(implode(', ', $sharedClauses));
            }
            if ($task['budget_basis'] === 'unknown' && in_array('max_price', $sameFacts, true) && ! empty($facts['budget_basis'])) {
                $task['budget_basis'] = $facts['budget_basis'];
            }
            $constraints['excluded_terms'] = array_values(array_unique(array_merge($excludedTerms, $task['clause_excluded_terms'] ?? [])));
            $constraints['excluded_names'] = array_values(array_unique(array_merge($excludedNames, $task['clause_excluded_names'] ?? [])));
            unset($task['clause_excluded_terms'], $task['clause_excluded_names']);
            $task['constraints'] = $constraints;
            if ($task['type'] === self::COMPARE && empty($task['reference'])) {
                $task['constraints']['destination_id'] = null;
                $task['constraints']['destination_name'] = null;
                $task['explicit_unknown_destination'] = false;
            }
            $task['explicit_constraints'] = array_keys(array_filter($parsed[$i], static fn (mixed $v): bool => $v !== null && $v !== []));
            if ($task['type'] !== self::COMPARE && empty($constraints['destination_id']) && count($destinations) > 1) {
                $task['clarification'] ??= 'Which destination should I use for this part of your request?';
            }
        }
        unset($task);
        $tasks = array_values(array_unique($tasks, SORT_REGULAR));
        $special = array_filter($tasks, fn (array $task): bool => in_array($task['type'], [self::ACTION, self::UNSUPPORTED, self::CLARIFY, self::DRAFT, self::KNOWLEDGE, self::COMPARE], true) || isset($task['clarification']));

        // A single request that rules something out is still planned work:
        // its exclusions only reach retrieval through the planned task.
        $hasScopedExclusions = array_filter($tasks, static fn (array $task): bool => ($task['constraints']['excluded_terms'] ?? []) !== []
            || ($task['constraints']['excluded_names'] ?? []) !== []) !== [];

        return ['handle' => count($tasks) > 1 || $special !== [] || $excluded !== [] || $excludedTerms !== [] || $excludedNames !== [] || $hasScopedExclusions || ($groups !== [] && $reference) || ($pending !== [] && $tasks !== []), 'tasks' => $tasks, 'source' => $source];
    }

    /**
     * A narrative information question that the catalog cannot answer. A
     * clause that also asks for hotels, rooms or activities stays a catalog
     * task; a route request from the user's own location stays a map task.
     *
     * @param  list<string>  $signals
     */
    private function knowledgeTopic(string $clause, array $signals): ?string
    {
        $topic = KnowledgeRetrievalService::topic($clause);
        if ($topic === null) {
            return null;
        }
        if (array_diff($signals, [IntentRouter::MAP_QUERY, IntentRouter::WEATHER_QUERY, IntentRouter::GENERAL_TALK, IntentRouter::LEGAL_QUERY]) !== []) {
            return null;
        }
        if (in_array(IntentRouter::MAP_QUERY, $signals, true) && preg_match('/\b(?:my\s+(?:current\s+)?location|from\s+here|how\s+far|distance|map)\b/iu', $clause)) {
            return null;
        }
        if (in_array(IntentRouter::WEATHER_QUERY, $signals, true) && $topic !== 'practical_guidance') {
            return null;
        }

        return $topic;
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
    /**
     * Whether a clause only restates constraints ("total budget 20000",
     * "for 2 pax", "within 15k") rather than asking for something.
     *
     * Such a clause belongs to its sibling tasks' constraints; turning it into
     * a task of its own spends a task slot and asks a second question about a
     * fact the user already stated.
     */
    private function constraintOnlyClause(string $clause): bool
    {
        if (preg_match('/\b(?:find|recommend|suggest|show|compare|look\s+for|hanap|pasyalan)\b/iu', $clause)) {
            return false;
        }

        $constraints = $this->router->extractConstraints($clause);
        $hasConstraintValue = ! empty($constraints['max_price'])
            || ! empty($constraints['pax'])
            || ! empty($constraints['nights'])
            || ! empty($constraints['days'])
            || ! empty($constraints['check_in_date']);

        if (! $hasConstraintValue) {
            return false;
        }

        // A named catalog entity makes the clause a request about that item.
        foreach (['hotel_name', 'room_name', 'activity_name', 'package_name', 'addon_name'] as $key) {
            if (! empty($constraints[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * A side-by-side comparison of two or more named hotels or rooms, or of
     * earlier results picked by position ("compare the first and second
     * room"). Named activities keep their existing comparison path.
     *
     * @return array<string, mixed>|null
     */
    private function comparisonTask(string $message, ChatSession $session): ?array
    {
        $comparative = '/\b(?:compare|comparison|vs\.?|versus|which\s+(?:is|one\s+is)\s+(?:better|cheaper|bigger)|difference\s+between|or|ikumpara|mas\s+(?:maganda|mura))\b/iu';
        if (! preg_match($comparative, $message)) {
            return null;
        }
        $constraints = $this->router->extractConstraints($message);
        $base = ['id' => 'task-1', 'type' => self::COMPARE, 'query' => mb_substr($message, 0, 1000), 'budget_basis' => $this->budgetBasis($message),
            'explicit_constraints' => array_keys(array_filter($constraints, static fn (mixed $v): bool => $v !== null && $v !== [])), 'reference' => false];

        preg_match_all('/\b('.self::ORDINALS.')\b/iu', $message, $ordinals);
        $positions = array_values(array_unique(array_map([self::class, 'ordinalPosition'], $ordinals[1])));
        $groups = $this->conversation->resultGroups($session);
        if (count($positions) >= 2 && $groups !== [] && preg_match('/\b(?:compare|vs\.?|versus|ikumpara|difference)\b/iu', $message)) {
            $type = match (true) {
                (bool) preg_match('/\brooms?\b/iu', $message) => 'room',
                (bool) preg_match('/\b(?:hotels?|resorts?)\b/iu', $message) => 'hotel',
                (bool) preg_match('/\b(?:activit(?:y|ies)|tours?)\b/iu', $message) => 'activity',
                default => count(array_unique(array_column($groups, 'type'))) === 1 ? $groups[0]['type'] : null,
            };
            $group = null;
            foreach ($groups as $candidate) {
                if ($candidate['type'] === $type) {
                    $group = $candidate;
                }
            }
            if ($group === null) {
                return [...$base, 'constraints' => $constraints, 'clarification' => 'Which results should I compare: hotels, rooms or activities?'];
            }
            $ids = array_map(fn (int $position): ?int => $group['result_ids'][$position] ?? null, $positions);
            if (in_array(null, $ids, true)) {
                return [...$base, 'constraints' => $constraints, 'clarification' => 'One of those positions is not in the displayed results. Which options should I compare?'];
            }
            foreach (['destination_id', 'destination_name', 'pax', 'check_in_date', 'check_out_date', 'nights'] as $key) {
                $constraints[$key] ??= $group['constraints'][$key] ?? null;
            }

            return [...$base, 'reference' => true, 'constraints' => $constraints, 'comparison' => ['type' => $type, 'ids' => $ids]];
        }

        foreach (['room', 'hotel'] as $type) {
            $items = $this->router->mentionedCatalogItems($message, $type);
            if (count($items) >= 2) {
                // Named items carry their own destinations; a destination
                // filter from the wording must not exclude one of them.
                $constraints['destination_id'] = null;
                $constraints['destination_name'] = null;

                return [...$base, 'constraints' => $constraints, 'comparison' => ['type' => $type, 'ids' => array_slice(array_column($items, 'id'), 0, 4)]];
            }
        }

        return null;
    }

    /**
     * Re-run the latest search of this type with the correction applied. The
     * original request text is reused so a correction such as "cheaper ones"
     * is not itself searched for as a feature; the correction's constraints
     * still override the inherited ones.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, mixed>
     */
    private function refinementTask(string $type, string $message, array $groups): array
    {
        $group = null;
        foreach ($groups as $candidate) {
            if (($candidate['intent'] ?? null) === $type) {
                $group = $candidate;
            }
        }
        $ordinal = (bool) preg_match('/\b(?:'.self::ORDINALS.')\b|\b(?:that|this|selected|iyon|yun)\s+(?:one|hotel|room|activity|tour)\b/iu', $message);
        $extra = ['refinement' => true];
        if (preg_match('/\b(?:cheaper|less\s+expensive|more\s+affordable|lower[-\s]priced|mas\s+mura)\b/iu', $message) && ! empty($group['result_ids'])) {
            $extra['relative_price'] = ['direction' => 'cheaper', 'type' => $group['type'], 'reference_ids' => $group['result_ids']];
        }
        $query = ! $ordinal && is_string($group['query'] ?? null) && $group['query'] !== '' ? $group['query'] : $message;

        return $this->task($type, $query, [...$extra, 'correction' => $message]);
    }

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
            (bool) preg_match('/\b(?:total|overall|whole\s+trip|for\s+(?:all\s+of\s+us|everyone)|kabuuan)\b/iu', $query) => 'trip_total',
            default => 'unknown',
        };
    }
}
