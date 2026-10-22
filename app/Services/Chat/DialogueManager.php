<?php

namespace App\Services\Chat;

use App\Models\ChatSession;
use App\Models\DestinationModel;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class DialogueManager
{
    private const TRAVEL_TASKS = [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ACTIVITY_SEARCH, IntentRouter::PACKAGE_SEARCH, IntentRouter::ADDON_SEARCH, IntentRouter::ITINERARY_QUERY, IntentRouter::AVAILABILITY_QUERY];

    public function __construct(private IntentRouter $router, private ConversationManager $conversation, private TripDetailsForm $tripDetails) {}

    /** @return array<string, mixed> */
    public function state(ChatSession $session): array
    {
        $state = $session->metadata['conversation_v2']['dialogue'] ?? [];
        if (! is_array($state) || ($state['version'] ?? null) !== 1 || ! is_array($state['tasks'] ?? null)) {
            $state = ['version' => 1, 'revision' => 0, 'tasks' => [], 'active_question' => null];
            foreach ($this->conversation->pendingTasks($session) as $pending) {
                if (in_array($pending['type'] ?? null, self::TRAVEL_TASKS, true)) {
                    $state['tasks'][] = $this->identify($pending, 'legacy');
                }
            }
        }
        $state['tasks'] = array_values(array_filter(array_slice($state['tasks'], -8), fn (mixed $task): bool => is_array($task) && in_array($task['type'] ?? null, self::TRAVEL_TASKS, true) && is_array($task['constraints'] ?? null) && is_string($task['persistent_id'] ?? null) && is_string($task['scope_id'] ?? null) && is_string($task['query'] ?? null) && in_array($task['status'] ?? null, ['collecting', 'ready', 'answered', 'suspended', 'cancelled'], true)));
        foreach ($state['tasks'] as &$task) {
            $task['slots'] = is_array($task['slots'] ?? null) ? $task['slots'] : [];
            $task['budget_basis'] ??= 'unknown';
        }
        unset($task);
        if (! is_array($state['active_question'] ?? null) || ! is_array($state['active_question']['task_ids'] ?? null) || ! is_string($state['active_question']['id'] ?? null) || ! is_string($state['active_question']['field'] ?? null)) {
            $state['active_question'] = null;
        }

        return $state;
    }

    /** @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public function prepare(array $plan, string $message, ChatSession $session, ?array $details = null): array
    {
        $state = $this->state($session);
        $state['revision'] = (int) ($state['revision'] ?? 0) + 1;
        $state['updated_at'] = now()->toIso8601String();
        if ($details !== null) {
            return $this->prepareDetails($state, $details, $message);
        }
        $question = $state['active_question'];
        $field = $question['field'] ?? null;
        $values = $this->router->dialogueConstraints($message, $field);
        $skip = (bool) preg_match('/^(?:skip|not\s+sure|dates\s+undecided|di\s+pa\s+(?:sure|alam)|wala\s+pa|kahit\s+ano|no\s+budget\s+preference)[.!]?$/iu', trim($message));
        $resume = (bool) preg_match('/^(?:continue(?:\s+planning)?|resume(?:\s+planning)?|ituloy)\b/iu', trim($message));
        $periodRefinement = $question === null
            && (bool) preg_match('/^(?:per\s+night|each\s+night|a\s+night|\/night|bawat\s+gabi|kada\s+gabi)[.!]?$/iu', trim($message))
            && array_filter($state['tasks'], static fn (array $task): bool => in_array($task['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ITINERARY_QUERY], true)
                && $task['status'] !== 'cancelled' && ($task['budget_basis'] ?? null) === 'per_person' && ! empty($task['constraints']['max_price'])) !== [];
        $correction = $periodRefinement || (bool) preg_match('/\b(?:actually|instead|change|make\s+that|same\s+budget|apat\s+kami)\b/iu', $message);
        $incoming = $plan['tasks'];
        if ($incoming === [] && preg_match('/\bplan\b.*\b(?:trip|vacation|holiday|bakasyon)\b/iu', $message)) {
            $incoming[] = ['type' => IntentRouter::ITINERARY_QUERY, 'query' => $message, 'constraints' => $values, 'budget_basis' => $values['budget_basis'] ?? 'unknown'];
        }
        if ($this->router->classify($message) === IntentRouter::SUPPORT_AGENT) {
            return [...$plan, 'dialogue' => $this->suspended($state)];
        }
        if (preg_match('/^(?:never\s*mind|nevermind|cancel\s+(?:this|planning|the\s+plan)|stop\s+planning|wag\s+na)[.!]?$/iu', trim($message))) {
            foreach ($state['tasks'] as &$task) {
                if (in_array($task['status'], ['collecting', 'suspended'], true)) {
                    $task['status'] = 'cancelled';
                }
            }
            unset($task);
            $state['active_question'] = null;

            return ['handle' => true, 'tasks' => [$this->statusTask('I have stopped this planning request. You can start a new one whenever you like.')], 'source' => 'dialogue_cancelled', 'dialogue' => $state];
        }
        if ($question === null && preg_match('/^\d+[.!]?$/u', trim($message))) {
            return ['handle' => true, 'tasks' => [$this->statusTask('Please clarify whether that number means travelers, days, or a displayed option.')], 'source' => 'dialogue_number', 'dialogue' => $state];
        }

        $hasAnswer = $question !== null && ($skip || $this->answers($field, $values));
        if ($hasAnswer && $field === 'budget_coverage' && ! preg_match('/\b(?:also|find|show|recommend|suggest|plus)\b/iu', $message)) {
            $incoming = [];
        }
        $work = [];
        if ($hasAnswer || $resume || ($correction && $state['tasks'] !== [])) {
            $scopeIds = array_values(array_unique(array_column(array_filter($state['tasks'], fn (array $t): bool => in_array($t['status'], ['collecting', 'suspended', 'answered'], true)), 'scope_id')));
            if (($resume || $correction) && count($scopeIds) > 1 && empty($values['destination_id'])) {
                return ['handle' => true, 'tasks' => [$this->statusTask('There is more than one trip in this conversation. Please name the destination you want to continue.')], 'source' => 'dialogue_scope', 'dialogue' => $state];
            }
            foreach ($state['tasks'] as $task) {
                if ($periodRefinement && (! in_array($task['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ITINERARY_QUERY], true)
                    || ($task['budget_basis'] ?? null) !== 'per_person' || empty($task['constraints']['max_price']))) {
                    continue;
                }
                $targeted = $hasAnswer ? in_array($task['persistent_id'], $question['task_ids'], true) : $task['status'] !== 'cancelled';
                if (! $targeted) {
                    continue;
                }
                if ($resume && ! empty($values['destination_id']) && ! empty($task['constraints']['destination_id']) && (int) $values['destination_id'] !== (int) $task['constraints']['destination_id']) {
                    continue;
                }
                $task = $this->merge($task, $values, $message, $state['revision']);
                unset($task['requirement'], $task['paused_reason'], $task['clarification']);
                if (! empty($values['invalid_dates']) || ! empty($values['invalid_pax']) || ! empty($values['invalid_duration'])) {
                    $task['invalid_attempts'] = (int) ($question['attempts'] ?? 0) + 1;
                    if ($task['invalid_attempts'] >= 2) {
                        $task['paused_reason'] = 'I could not confirm a valid value, so I have paused this request. Say “continue planning” when you are ready.';
                    }
                }
                if ($skip) {
                    $task['slots'][$field] = ['value' => null, 'status' => 'undecided', 'source_turn' => $state['revision'], 'provenance' => 'user'];
                    if ($field === 'dates' && $task['type'] === IntentRouter::AVAILABILITY_QUERY) {
                        $task['type'] = IntentRouter::ROOM_SEARCH;
                        $task['discovery_only'] = true;
                    } elseif ($field === 'dates' && $task['type'] === IntentRouter::ITINERARY_QUERY) {
                        $task['constraints']['max_price'] = null;
                        $task['draft_itinerary'] = true;
                        $task['discovery_only'] = true;
                    } elseif (in_array($field, ['budget_scope', 'budget_basis', 'budget_period', 'budget_coverage'], true)) {
                        $task['constraints']['max_price'] = null;
                        $task['budget_basis'] = 'unknown';
                        unset($task['joint_budget'], $task['budget_scope'], $task['budget_period'], $task['budget_coverage']);
                    } else {
                        $task['paused_reason'] = 'This request is paused until that detail is known. I can still help with general recommendations.';
                    }
                }
                $work[] = $task;
            }
            $incoming = array_values(array_filter($incoming, function (array $candidate) use ($work, $correction): bool {
                foreach ($work as $task) {
                    if ($candidate['type'] === $task['type'] && ($correction || empty($candidate['constraints']['destination_id']) || (int) $candidate['constraints']['destination_id'] === (int) ($task['constraints']['destination_id'] ?? 0))) {
                        return false;
                    }
                }

                return true;
            }));
        } elseif ($question !== null && $incoming === []) {
            $volunteered = $this->volunteered($values);
            foreach ($state['tasks'] as $task) {
                if (! in_array($task['persistent_id'], $question['task_ids'], true)) {
                    continue;
                }
                // The user answered with something else useful ("2 people" to
                // a duration question). Keep that detail, acknowledge it and
                // ask the outstanding question again — no invalid strike, and
                // never the same question against unchanged state.
                if ($volunteered !== [] && $this->compatible($task, $values)) {
                    $task = $this->merge($task, $values, $message, $state['revision']);
                    unset($task['paused_reason'], $task['clarification']);
                    $task['requirement'] = $field;
                    $task['invalid_attempts'] = (int) ($question['attempts'] ?? 0);
                    $task['acknowledged'] = $volunteered;
                    $work[] = $task;

                    continue;
                }
                $attempts = (int) ($question['attempts'] ?? 0) + 1;
                $task['invalid_attempts'] = $attempts;
                $task['slots'][$field] = ['value' => null, 'status' => 'invalid', 'source_turn' => $state['revision'], 'provenance' => 'user'];
                $task['requirement'] = $field;
                if ($attempts >= 2) {
                    unset($task['requirement']);
                    $task['paused_reason'] = 'I could not confirm that detail, so I have paused this request. Say “continue planning” when you are ready.';
                }
                $work[] = $task;
            }
        } elseif ($question !== null) {
            $state = $this->suspended($state);
        }

        $turnScope = $work[0]['scope_id'] ?? (string) Str::uuid();
        $knownDestinations = array_unique(array_filter(array_column(array_column($state['tasks'], 'constraints'), 'destination_id')));
        foreach ($incoming as $task) {
            if (in_array($task['type'], self::TRAVEL_TASKS, true)) {
                if (count($knownDestinations) > 1 && empty($values['destination_id']) && ! in_array('destination_id', $task['explicit_constraints'] ?? [], true) && empty($task['entity'])) {
                    $task['constraints']['destination_id'] = null;
                    $task['constraints']['destination_name'] = null;
                    unset($task['clarification']);
                }
                $task = $this->reuse($state, $task) ?? $this->identify($task, $turnScope);
                $task = $this->merge($task, $this->router->dialogueConstraints(count($incoming) === 1 ? $message : $task['query']), $task['query'], $state['revision']);
                if (array_key_exists('include_accommodation', $values) && count($knownDestinations) <= 1 && $this->compatible($task, $values)) {
                    $task['constraints']['include_accommodation'] = $values['include_accommodation'];
                }
                foreach (['pax', 'days', 'nights', 'check_in_date', 'check_out_date'] as $key) {
                    if (! empty($values[$key]) && empty($task['constraints'][$key]) && $this->compatible($task, $values)) {
                        $task['constraints'][$key] = $values[$key];
                    }
                }
                if ($hasAnswer && $this->compatible($task, $values)) {
                    foreach (['destination_id', 'destination_name'] as $key) {
                        $task['constraints'][$key] ??= $values[$key] ?? $work[0]['constraints'][$key] ?? null;
                    }
                }
            }
            $work[] = $task;
        }
        if (($values['budget_basis'] ?? null) === 'trip_total') {
            $sharedTasks = array_values(array_filter($work, fn (array $t): bool => in_array($t['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ACTIVITY_SEARCH], true)));
            if (count($sharedTasks) > 1 && count(array_unique(array_filter(array_column(array_column($sharedTasks, 'constraints'), 'destination_id')))) <= 1) {
                $itinerary = $sharedTasks[0];
                $itinerary['type'] = IntentRouter::ITINERARY_QUERY;
                $itinerary['query'] = 'Plan a combined hotel and activities itinerary in '.($itinerary['constraints']['destination_name'] ?? 'the requested destination');
                $itinerary['persistent_id'] = (string) Str::uuid();
                $itinerary['budget_basis'] = 'trip_total';
                $itinerary['constraints']['max_price'] ??= $values['max_price'] ?? $itinerary['joint_budget']['amount'] ?? max(array_column(array_column($sharedTasks, 'constraints'), 'max_price'));
                $itinerary['constraints']['budget_mode'] ??= $values['budget_mode'];
                $work[] = $itinerary;
                foreach ($work as &$task) {
                    if (in_array($task['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ACTIVITY_SEARCH], true)) {
                        $task['combined_budget_pending'] = true;
                    }
                }
                unset($task);
            }
        }
        if ($work === []) {
            return [...$plan, 'dialogue' => $state];
        }
        $monetary = array_filter($work, fn (array $t): bool => in_array($t['type'], self::TRAVEL_TASKS, true) && ! empty($t['constraints']['max_price']));
        $recommendations = array_filter($work, fn (array $t): bool => in_array($t['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ACTIVITY_SEARCH], true));
        if (! $hasAnswer && count($recommendations) > 1 && count($monetary) === 1) {
            $budgetTask = reset($monetary);
            if (($budgetTask['budget_basis'] ?? 'unknown') === 'unknown' && ($budgetTask['constraints']['budget_mode'] ?? 'target') === 'target') {
                foreach ($work as &$task) {
                    if (in_array($task['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH, IntentRouter::ACTIVITY_SEARCH], true)) {
                        $task['budget_scope'] = 'unresolved';
                        $task['budget_amount'] = $budgetTask['constraints']['max_price'];
                    }
                }
                unset($task);
            }
        }
        // Nothing is "shared" between a single request and itself, so a lone
        // spendable task owns the whole amount and is never asked how to
        // split it.
        $spendableCount = count(array_filter($work, fn (array $t): bool => in_array($t['type'], self::TRAVEL_TASKS, true)));
        foreach ($work as $i => &$task) {
            $task['id'] = 'task-'.($i + 1);
            $task['budget_basis'] ??= 'unknown';
            if ($spendableCount <= 1) {
                unset($task['joint_budget']);
            }
            if (! in_array($task['type'], self::TRAVEL_TASKS, true)) {
                continue;
            }
            $task['status'] = 'ready';
            if (! isset($task['requirement']) && ! isset($task['paused_reason'])) {
                $requirement = $this->requirement($task);
                if ($requirement !== null) {
                    $task['requirement'] = $requirement;
                }
            }
            if (isset($task['requirement'])) {
                $task['status'] = 'collecting';
            }
            if ($task['type'] === IntentRouter::ITINERARY_QUERY && empty($task['constraints']['check_in_date']) && (empty($task['constraints']['max_price']) || ($task['slots']['dates']['status'] ?? null) === 'undecided')) {
                $task['draft_itinerary'] = true;
            }
        }
        unset($task);

        return ['handle' => true, 'tasks' => $work, 'source' => 'guided_dialogue', 'dialogue' => $state];
    }

    /** @param array<string, mixed> $state
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function prepareDetails(array $state, array $details, string $message): array
    {
        $values = $details['values'];
        $work = [];
        foreach ($state['tasks'] as $task) {
            if ($task['scope_id'] !== $details['scope_id'] || $task['status'] === 'cancelled') {
                continue;
            }
            unset($task['requirement'], $task['paused_reason'], $task['clarification'], $task['joint_budget'], $task['budget_scope'], $task['combined_budget_pending']);
            $task = $this->merge($task, $values, $message, $state['revision']);
            if (! empty($values['budget_basis'])) {
                $task['budget_basis'] = $values['budget_basis'];
                $task['slots']['budget_basis'] = ['value' => $values['budget_basis'], 'status' => 'provided', 'provenance' => 'user', 'source_turn' => $state['revision']];
            }
            foreach (['budget_period', 'budget_coverage'] as $key) {
                if (array_key_exists($key, $values) && $values[$key] === null) {
                    unset($task[$key], $task['slots'][$key]);
                }
            }
            foreach (['pax', 'days', 'nights', 'max_price', 'check_in_date', 'check_out_date', 'preferences', 'notes'] as $key) {
                if (array_key_exists($key, $values)) {
                    $task['constraints'][$key] = $values[$key];
                }
            }
            if (array_key_exists('max_price', $values) && $values['max_price'] === null) {
                $task['budget_basis'] = 'unknown';
                unset($task['budget_period'], $task['budget_coverage']);
            }
            if (in_array($task['budget_coverage'] ?? null, ['activities', 'lodging'], true)) {
                unset($task['joint_budget']);
            }
            if (isset($values['days']) && ! isset($values['check_in_date']) && empty($values['dates_undecided'])) {
                $task['constraints']['check_in_date'] = $task['constraints']['check_out_date'] = null;
            }
            if (! empty($values['dates_undecided'])) {
                $task['slots']['dates'] = ['value' => null, 'status' => 'undecided', 'provenance' => 'user', 'source_turn' => $state['revision']];
                $task['constraints']['check_in_date'] = $task['constraints']['check_out_date'] = null;
                if ($task['type'] === IntentRouter::AVAILABILITY_QUERY) {
                    $task['type'] = IntentRouter::ROOM_SEARCH;
                }
            } elseif (! empty($values['check_in_date'])) {
                unset($task['slots']['dates'], $task['draft_itinerary'], $task['discovery_only']);
            } elseif (array_key_exists('dates_undecided', $values) && $values['dates_undecided'] === false) {
                unset($task['slots']['dates']);
            }
            $preferenceText = implode(' ', $task['constraints']['preferences'] ?? []).' '.($task['constraints']['notes'] ?? '');
            $task['query'] = $task['type'].' in '.($task['constraints']['destination_name'] ?? '').' '.$preferenceText;
            if (array_key_exists('notes', $values)) {
                $exclusions = $this->router->extractExclusions($preferenceText);
                foreach (['excluded_terms' => 'terms', 'excluded_names' => 'names'] as $key => $source) {
                    $original = array_diff($task['constraints'][$key] ?? [], $task['constraints']['form_'.$key] ?? []);
                    $task['constraints']['form_'.$key] = $exclusions[$source] ?? [];
                    $task['constraints'][$key] = array_values(array_unique(array_merge($original, $exclusions[$source] ?? [])));
                }
            }
            if ($task['type'] === IntentRouter::ITINERARY_QUERY && empty($task['constraints']['check_in_date']) && ($task['slots']['dates']['status'] ?? null) === 'undecided') {
                $task['draft_itinerary'] = true;
            }
            $requirement = $this->requirement($task);
            $task['status'] = $requirement === null ? 'ready' : 'collecting';
            if ($requirement !== null) {
                $task['requirement'] = $requirement;
            }
            $task['id'] = 'task-'.(count($work) + 1);
            $work[] = $task;
        }

        if (($values['budget_basis'] ?? null) === 'trip_total' && ! in_array($values['budget_coverage'] ?? null, ['activities', 'lodging'], true)
            && array_intersect(array_column($work, 'type'), [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH]) !== []
            && in_array(IntentRouter::ACTIVITY_SEARCH, array_column($work, 'type'), true)
            && ! in_array(IntentRouter::ITINERARY_QUERY, array_column($work, 'type'), true)) {
            $itinerary = $work[0];
            $itinerary['type'] = IntentRouter::ITINERARY_QUERY;
            $itinerary['persistent_id'] = (string) Str::uuid();
            $itinerary['id'] = 'task-'.(count($work) + 1);
            unset($itinerary['requirement']);
            if (! empty($values['dates_undecided'])) {
                $itinerary['draft_itinerary'] = true;
            }
            if (($requirement = $this->requirement($itinerary)) !== null) {
                $itinerary['requirement'] = $requirement;
            }
            foreach ($work as &$part) {
                $part['combined_budget_pending'] = true;
            }
            unset($part);
            $work[] = $itinerary;
        }

        return ['handle' => true, 'tasks' => $work, 'source' => 'trip_details', 'dialogue' => $state];
    }

    /** @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function identify(array $task, string $scope): array
    {
        $scopeId = ! empty($task['constraints']['destination_id']) ? 'destination-'.$task['constraints']['destination_id'] : $scope;
        if (! empty($task['constraints']['check_in_date'])) {
            $scopeId .= ':'.$task['constraints']['check_in_date'].':'.($task['constraints']['check_out_date'] ?? '');
        }

        return [...$task, 'persistent_id' => (string) Str::uuid(), 'scope_id' => $scopeId, 'status' => 'collecting', 'slots' => [], 'budget_basis' => $task['budget_basis'] ?? 'unknown'];
    }

    /** @param array<string, mixed> $task
     * @param  array<string, mixed>  $values
     */
    private function compatible(array $task, array $values): bool
    {
        return empty($values['destination_id']) || empty($task['constraints']['destination_id']) || (int) $values['destination_id'] === (int) $task['constraints']['destination_id'];
    }

    /** @param array<string, mixed> $task
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function merge(array $task, array $values, string $message, int $turn): array
    {
        if (($task['budget_basis'] ?? null) === 'per_person'
            && ($values['budget_basis'] ?? null) === 'per_night'
            && empty($values['max_price'])
            && ! preg_match('/\b(?:for\s+(?:both|everyone|the\s+(?:whole\s+)?group)|per\s+(?:room|booking)|not\s+per\s+person)\b/iu', $message)) {
            $values['budget_basis'] = null;
            $values['budget_period'] = 'per_night';
        }
        $changedDestination = ! $this->compatible($task, $values);
        if ($changedDestination) {
            foreach (['hotel_id', 'hotel_name', 'room_id', 'room_name', 'activity_id', 'activity_name', 'selected_activity_ids', 'preserve_selected_stay'] as $key) {
                unset($task['constraints'][$key]);
            }
            unset($task['entity']);
            $task['scope_id'] = 'destination-'.$values['destination_id'];
        }
        foreach ($values as $key => $value) {
            if ($value === null || $value === [] || in_array($key, ['invalid_dates', 'invalid_pax', 'invalid_duration', 'place_names', 'field_intent', 'budget_basis', 'budget_period', 'budget_coverage'], true)) {
                continue;
            }
            $task['constraints'][$key] = $value;
            $task['slots'][$key] = ['value' => $value, 'status' => 'provided', 'source_turn' => $turn, 'provenance' => 'user'];
        }
        if (! empty($values['invalid_dates'])) {
            foreach (['check_in_date', 'check_out_date', 'nights'] as $key) {
                $task['constraints'][$key] = null;
            }
            $task['slots']['dates'] = ['value' => null, 'status' => 'invalid', 'source_turn' => $turn, 'provenance' => 'user'];
        } elseif (! empty($task['constraints']['check_in_date']) && ! empty($task['constraints']['check_out_date'])) {
            $nights = (int) Carbon::parse($task['constraints']['check_in_date'])->diffInDays(Carbon::parse($task['constraints']['check_out_date']));
            $task['constraints']['nights'] = $nights;
            $task['constraints']['days'] = $nights + 1;
        }
        foreach (['pax' => 'invalid_pax', 'days' => 'invalid_duration', 'nights' => 'invalid_duration'] as $key => $invalid) {
            if (! empty($values[$invalid])) {
                $task['constraints'][$key] = null;
                $task['slots'][$key] = ['value' => null, 'status' => 'invalid', 'source_turn' => $turn, 'provenance' => 'user'];
            }
        }
        if (! empty($values['days']) && empty($values['nights']) && empty($values['check_in_date'])) {
            $task['constraints']['nights'] = null;
        }
        foreach (['budget_period', 'budget_coverage'] as $narrower) {
            if (! empty($values[$narrower])) {
                $task[$narrower] = $values[$narrower];
                $task['slots'][$narrower] = ['value' => $values[$narrower], 'status' => 'provided', 'source_turn' => $turn, 'provenance' => 'user'];
            }
        }
        if (! empty($values['budget_basis'])) {
            $basis = $values['budget_basis'];
            // A basis the user states is always kept: discarding it resets the
            // conversation's progress and re-asks the same question forever.
            // What a basis cannot settle on its own is asked as a narrower
            // question by requirement() instead.
            $task['slots']['budget_basis'] = ['value' => $basis, 'status' => 'provided', 'source_turn' => $turn, 'provenance' => 'user'];
            $task['constraints']['max_price'] ??= $task['budget_amount'] ?? null;
            $task['budget_basis'] = $basis;
            unset($task['budget_scope'], $task['budget_amount'], $task['joint_budget']);
            if ($basis === 'trip_total') {
                $task['joint_budget'] = ['amount' => $task['constraints']['max_price'] ?? null, 'basis' => 'trip_total'];
            }
        }
        if ($changedDestination || preg_match('/\b(?:actually|instead)\b/iu', $message)) {
            $task['query'] = $task['type'].' in '.($task['constraints']['destination_name'] ?? '').' '.$message;
        }
        $preferences = trim(implode(' ', $task['constraints']['preferences'] ?? []).' '.($task['constraints']['notes'] ?? ''));
        if ($preferences !== '' && ! str_contains($task['query'], $preferences)) {
            $task['query'] .= ' Preferences: '.$preferences;
        }

        return $task;
    }

    /** @param array<string, mixed> $values */
    private function answers(?string $field, array $values): bool
    {
        return match ($field) {
            'destination' => ! empty($values['destination_id']),
            'dates' => ! empty($values['check_in_date']) || ! empty($values['invalid_dates']),
            'days' => ! empty($values['days']) || ! empty($values['nights']),
            'pax' => isset($values['pax']) || ! empty($values['invalid_pax']),
            'nights' => isset($values['nights']) || ! empty($values['invalid_duration']),
            'budget_scope', 'budget_basis', 'budget_allocation' => ! empty($values['budget_basis']),
            'budget_period' => ! empty($values['budget_period']) || ! empty($values['budget_basis']),
            'budget_coverage' => ! empty($values['budget_coverage']),
            default => isset($values[$field]),
        };
    }

    /** @param array<string, mixed> $task */
    private function requirement(array $task): ?string
    {
        $c = $task['constraints'];
        if (! $this->budgetApplies($task)) {
            $c['max_price'] = null;
        }
        if (empty($c['destination_id']) && empty($c['hotel_id']) && empty($c['room_id']) && empty($c['activity_name']) && empty($c['package_name']) && empty($c['addon_id']) && empty($task['entity'])) {
            return 'destination';
        }
        if ($task['type'] === IntentRouter::AVAILABILITY_QUERY && (empty($c['check_in_date']) || empty($c['check_out_date']))) {
            return 'dates';
        }
        if (($task['slots']['dates']['status'] ?? null) === 'invalid') {
            return 'dates';
        }
        if ($task['type'] === IntentRouter::AVAILABILITY_QUERY && empty($c['pax'])) {
            return 'pax';
        }
        $basis = $task['budget_basis'] ?? 'unknown';
        $basisAnswered = ($task['slots']['budget_basis']['status'] ?? null) === 'provided';
        $periodAnswered = isset($task['slots']['budget_period']);
        $coverageAnswered = isset($task['slots']['budget_coverage']);

        // A per-person or per-activity budget stated for a stay is a valid
        // statement; what it leaves open is the headcount it multiplies by and
        // the period it covers — not the basis again.
        if (in_array($task['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH], true) && ! empty($c['max_price'])) {
            if (in_array($basis, ['per_person', 'per_activity'], true)) {
                if (empty($c['pax'])) {
                    return 'pax';
                }
                if (! $periodAnswered) {
                    return 'budget_period';
                }
            }
            // A whole-stay ceiling cannot be checked against nightly rates
            // until the number of nights is known, and a ceiling must never
            // certify a card it has not actually compared.
            $wholeStay = $basis === 'trip_total' || ($task['budget_period'] ?? null) === 'total';
            if ($wholeStay && empty($c['nights']) && ($c['budget_mode'] ?? 'target') === 'ceiling') {
                return 'nights';
            }

            return null;
        }

        // A per-person budget for activities compares directly with their
        // per-person rates, so it needs no headcount. Add-on tiers are priced
        // per group size and still need one.
        if ($task['type'] === IntentRouter::ADDON_SEARCH
            && ! empty($c['max_price']) && $basis === 'per_person' && empty($c['pax'])) {
            return 'pax';
        }

        if ($task['type'] === IntentRouter::ITINERARY_QUERY) {
            if (empty($c['days']) && empty($c['nights'])) {
                return 'days';
            }
            if (! empty($c['max_price'])) {
                if (empty($c['pax'])) {
                    return 'pax';
                }
                if ($basis === 'unknown' && ! $basisAnswered) {
                    return 'budget_basis';
                }
                // A nightly figure may cover lodging only; it is never
                // multiplied into an all-inclusive trip ceiling unasked.
                if ($basis === 'per_night' && ! $coverageAnswered) {
                    return 'budget_coverage';
                }
                if ($basis === 'per_person' && ! $periodAnswered) {
                    return 'budget_period';
                }
                if (empty($c['nights']) && ($task['slots']['dates']['status'] ?? null) !== 'undecided') {
                    return 'nights';
                }
                if (empty($c['check_in_date']) && ($task['slots']['dates']['status'] ?? null) !== 'undecided') {
                    return 'dates';
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $task */
    public function budgetApplies(array $task): bool
    {
        return match ($task['budget_coverage'] ?? null) {
            'lodging' => ! in_array($task['type'], [IntentRouter::ACTIVITY_SEARCH, IntentRouter::ADDON_SEARCH], true),
            'activities' => ! in_array($task['type'], [IntentRouter::HOTEL_SEARCH, IntentRouter::ROOM_SEARCH], true),
            default => true,
        };
    }

    /**
     * A compact summary of what the live trip scope already knows, with an
     * edit prompt per field.
     *
     * Each entry targets one field in one trip scope, so changing the dates
     * cannot discard an unrelated request. Fields with no value are left out:
     * those are what the question machinery is already asking about.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private function tripSummary(array $state): ?array
    {
        $active = null;
        foreach ($state['tasks'] as $task) {
            if (in_array($task['status'] ?? '', ['cancelled', 'suspended'], true)) {
                continue;
            }
            $active = $task;
        }
        if ($active === null) {
            return null;
        }

        $constraints = $active['constraints'] ?? [];
        $destination = $constraints['destination_name'] ?? null;
        $scopeLabel = $destination !== null ? ' for '.$destination : '';
        $items = [];

        $append = static function (string $field, string $label, ?string $value, string $editNoun) use (&$items, $scopeLabel): void {
            if ($value === null || $value === '') {
                return;
            }
            // The destination names the scope, so repeating it would read
            // "the destination for Boracay".
            $scope = $field === 'destination' ? '' : $scopeLabel;
            $items[] = [
                'field' => $field,
                'label' => $label,
                'value' => $value,
                // Fills the composer for the user to complete, so a tap never
                // submits a value they did not choose.
                'edit_prompt' => 'Change the '.$editNoun.$scope.' to ',
            ];
        };

        $append('destination', 'Destination', $destination, 'destination');

        $dates = ! empty($constraints['check_in_date']) && ! empty($constraints['check_out_date'])
            ? $constraints['check_in_date'].' → '.$constraints['check_out_date']
            : null;
        $append('dates', 'Dates', $dates, 'dates');

        $append('pax', 'Travelers', ! empty($constraints['pax']) ? (string) $constraints['pax'] : null, 'number of travelers');

        $length = match (true) {
            $dates !== null => ($constraints['days'] ?? ($constraints['nights'] + 1)).' days, '.$constraints['nights'].' nights',
            ! empty($constraints['days']) => $constraints['days'].' day'.($constraints['days'] > 1 ? 's' : ''),
            ! empty($constraints['nights']) => $constraints['nights'].' night'.($constraints['nights'] > 1 ? 's' : ''),
            default => null,
        };
        $append('days', 'Length', $length, 'trip length');

        if (! empty($constraints['max_price'])) {
            $basis = match ($active['budget_basis'] ?? 'unknown') {
                'per_night' => ' per night',
                'per_person' => ' per person',
                'per_activity' => ' per activity',
                'trip_total' => ' for the whole trip',
                default => '',
            };
            if (($active['budget_basis'] ?? null) === 'per_person') {
                $basis .= ($active['budget_period'] ?? null) === 'per_night' ? ' per night' : (($active['budget_period'] ?? null) === 'total' ? ' for the whole stay' : '');
            }
            $mode = ($constraints['budget_mode'] ?? 'target') === 'ceiling' ? 'up to ' : 'around ';
            $append('budget', 'Budget', $mode.'₱'.number_format((int) $constraints['max_price']).$basis, 'budget');
        }

        if ($items === []) {
            return null;
        }

        return [
            'scope_id' => $active['scope_id'],
            'heading' => $destination !== null ? 'Your '.$destination.' trip so far' : 'Your trip so far',
            'items' => $items,
        ];
    }

    /**
     * The input control for a question, or null when free text is the only
     * sensible answer.
     *
     * Option values are server-side identifiers; a client label is never
     * trusted on the way back in.
     *
     * @param  array<string, mixed>  $next
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>|null
     */
    private function questionControl(array $next, array $plan): ?array
    {
        $field = $next['field'];
        $skip = match ($field) {
            'dates' => ['label' => 'Dates undecided', 'value' => 'Dates undecided'],
            'budget_scope', 'budget_basis', 'budget_period', 'budget_coverage' => ['label' => 'No budget preference', 'value' => 'No budget preference'],
            default => null,
        };

        $control = match ($field) {
            'destination' => [
                'type' => 'select',
                'submit_label' => 'Use this destination',
                'fields' => [[
                    'name' => 'destination',
                    'label' => 'Destination',
                    'input' => 'select',
                    'required' => true,
                    'options' => $this->destinationOptions(),
                ]],
            ],
            'dates' => [
                'type' => 'date_range',
                'submit_label' => 'Use these dates',
                'fields' => [
                    ['name' => 'check_in', 'label' => 'Check-in', 'input' => 'date', 'required' => true, 'min' => now()->toDateString(), 'max' => now()->addYear()->toDateString()],
                    ['name' => 'check_out', 'label' => 'Check-out', 'input' => 'date', 'required' => true, 'min' => now()->addDay()->toDateString(), 'max' => now()->addYear()->addDay()->toDateString()],
                ],
            ],
            'pax' => [
                'type' => 'count',
                'submit_label' => 'Use this number',
                'fields' => [[
                    'name' => 'pax',
                    'label' => 'Travelers (including children)',
                    'input' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 30,
                    'step' => 1,
                ]],
            ],
            'days' => [
                'type' => 'count',
                'submit_label' => 'Use this length',
                'fields' => [[
                    'name' => 'days',
                    'label' => 'Days',
                    'input' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 30,
                    'step' => 1,
                ]],
            ],
            'nights' => [
                'type' => 'count',
                'submit_label' => 'Use these nights',
                'fields' => [[
                    'name' => 'nights',
                    'label' => 'Paid hotel nights',
                    'input' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 30,
                    'step' => 1,
                ]],
            ],
            'budget_scope', 'budget_basis' => [
                'type' => 'choices',
                'submit_label' => 'Apply',
                'fields' => [[
                    'name' => 'budget_basis',
                    'label' => 'How should I read this budget?',
                    'input' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'per_night', 'label' => 'Per night'],
                        ['value' => 'per_person', 'label' => 'Per person'],
                        ['value' => 'per_activity', 'label' => 'Per activity'],
                        ['value' => 'trip_total', 'label' => 'Whole trip total'],
                    ],
                ]],
            ],
            'budget_period' => [
                'type' => 'choices',
                'submit_label' => 'Apply',
                'fields' => [[
                    'name' => 'budget_period',
                    'label' => 'What period does the amount cover?',
                    'input' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'per_night', 'label' => 'Per night'],
                        ['value' => 'total', 'label' => 'The whole stay'],
                    ],
                ]],
            ],
            'budget_coverage' => [
                'type' => 'choices',
                'submit_label' => 'Apply',
                'fields' => [[
                    'name' => 'budget_coverage',
                    'label' => 'What does the nightly amount cover?',
                    'input' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'lodging', 'label' => 'The hotel only'],
                        ['value' => 'all_inclusive', 'label' => 'Everything, including activities'],
                    ],
                ]],
            ],
            default => null,
        };

        if ($control === null) {
            return null;
        }

        $control['field'] = $field;
        $control['skip'] = $skip;
        $control['manual_hint'] = 'You can also just type your answer.';

        return $control;
    }

    /**
     * Destinations a question may offer, from the visible catalog only.
     *
     * @return list<array{value: string, label: string}>
     */
    private function destinationOptions(): array
    {
        $options = [];
        foreach (DestinationModel::orderBy('name')->get(['id', 'name']) as $destination) {
            $options[] = ['value' => (string) $destination->id, 'label' => (string) $destination->name];
        }

        return array_slice($options, 0, 50);
    }

    /**
     * Turns a validated structured answer into the plain sentence the user
     * would have typed, so typed and tapped answers run through exactly one
     * merge and validation path and the transcript stays readable.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $answer
     * @return array{message: string}|array{errors: array<string, string>}
     */
    public function answerFromControl(array $question, array $answer): array
    {
        $control = $question['control'] ?? null;
        if (! is_array($control)) {
            return ['errors' => ['answer' => 'This question does not accept a structured answer.']];
        }

        $skipValue = $control['skip']['value'] ?? null;
        $allowedFields = [...array_column($control['fields'], 'name'), 'skip'];
        if (array_diff(array_keys($answer), $allowedFields) !== [] || (isset($answer['skip']) && ! is_bool($answer['skip']))) {
            return ['errors' => ['answer' => 'Please use the fields offered for this question.']];
        }
        if (($answer['skip'] ?? false) === true) {
            return $skipValue === null
                ? ['errors' => ['answer' => 'This question cannot be skipped.']]
                : ['message' => (string) $skipValue];
        }

        $errors = [];
        $values = [];
        foreach ($control['fields'] as $definition) {
            $name = $definition['name'];
            $raw = $answer[$name] ?? null;
            if ($raw !== null && ! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
                $errors[$name] = 'Please provide a single value for '.$definition['label'].'.';

                continue;
            }
            if ($raw === null || $raw === '') {
                if (! empty($definition['required'])) {
                    $errors[$name] = $definition['label'].' is required.';
                }

                continue;
            }
            if (($definition['input'] ?? '') === 'number') {
                if (! is_numeric($raw) || (int) $raw != $raw) {
                    $errors[$name] = $definition['label'].' must be a whole number.';

                    continue;
                }
                $number = (int) $raw;
                if ($number < ($definition['min'] ?? 1) || $number > ($definition['max'] ?? 30)) {
                    $errors[$name] = $definition['label'].' must be between '.($definition['min'] ?? 1).' and '.($definition['max'] ?? 30).'.';

                    continue;
                }
                $values[$name] = $number;

                continue;
            }
            if (($definition['input'] ?? '') === 'date') {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', (string) $raw);
                } catch (\Throwable) {
                    $date = null;
                }
                if ($date === null || $date->toDateString() !== (string) $raw) {
                    $errors[$name] = $definition['label'].' must be a calendar date (YYYY-MM-DD).';

                    continue;
                }
                if (isset($definition['min']) && $date->toDateString() < $definition['min']) {
                    $errors[$name] = $definition['label'].' cannot be earlier than '.$definition['min'].'.';

                    continue;
                }
                if (isset($definition['max']) && $date->toDateString() > $definition['max']) {
                    $errors[$name] = $definition['label'].' cannot be later than '.$definition['max'].'.';

                    continue;
                }
                $values[$name] = $date->toDateString();

                continue;
            }
            // select / radio: the value must be one this server offered.
            $allowed = array_column($definition['options'] ?? [], 'value');
            if (! in_array((string) $raw, $allowed, true)) {
                $errors[$name] = 'Please choose one of the offered options.';

                continue;
            }
            $values[$name] = (string) $raw;
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        return match ($control['type']) {
            'select' => ['message' => $this->destinationLabel($control, $values['destination'] ?? '')],
            'date_range' => $values['check_out'] <= $values['check_in']
                ? ['errors' => ['check_out' => 'Check-out must be after check-in.']]
                : ['message' => $values['check_in'].' to '.$values['check_out']],
            'count' => ['message' => match ($control['field']) {
                'pax' => $values['pax'].' travelers',
                'days' => $values['days'].' days',
                default => $values['nights'].' nights',
            }],
            'choices' => ['message' => match ($values[array_key_first($values)] ?? '') {
                'per_night' => 'per night',
                'per_person' => 'per person',
                'per_activity' => 'per activity',
                'trip_total' => 'for the whole trip total',
                'total' => 'for the whole stay',
                'lodging' => 'the hotel only',
                'all_inclusive' => 'everything including activities',
                default => '',
            }],
            default => ['errors' => ['answer' => 'Unsupported answer type.']],
        };
    }

    /**
     * @param  array<string, mixed>  $control
     */
    private function destinationLabel(array $control, string $value): string
    {
        foreach ($control['fields'][0]['options'] ?? [] as $option) {
            if ($option['value'] === $value) {
                return (string) $option['label'];
            }
        }

        return $value;
    }

    /**
     * User-facing name for a question field. Internal slot keys never reach
     * the transcript.
     */
    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'destination' => 'a destination',
            'dates' => 'check-in and check-out dates',
            'days' => 'the trip length',
            'nights' => 'the number of hotel nights',
            'pax' => 'the number of travelers',
            'budget_scope' => 'what the budget covers',
            'budget_basis' => 'how to read the budget',
            'budget_period' => 'the period the budget covers',
            'budget_coverage' => 'what the nightly budget covers',
            'budget_allocation' => 'how to split the budget',
            default => 'one more detail',
        };
    }

    /**
     * Readable confirmations for details the user volunteered instead of
     * answering the outstanding question.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function volunteered(array $values): array
    {
        $noted = [];
        if (! empty($values['destination_name'])) {
            $noted[] = (string) $values['destination_name'];
        }
        if (! empty($values['pax'])) {
            $noted[] = $values['pax'].' traveler'.($values['pax'] > 1 ? 's' : '');
        }
        if (! empty($values['days'])) {
            $noted[] = $values['days'].' day'.($values['days'] > 1 ? 's' : '');
        }
        if (! empty($values['nights'])) {
            $noted[] = $values['nights'].' night'.($values['nights'] > 1 ? 's' : '');
        }
        if (! empty($values['check_in_date']) && ! empty($values['check_out_date'])) {
            $noted[] = $values['check_in_date'].' to '.$values['check_out_date'];
        }
        if (! empty($values['max_price'])) {
            $noted[] = '₱'.number_format((int) $values['max_price']);
        }
        if (! empty($values['budget_basis'])) {
            $noted[] = match ($values['budget_basis']) {
                'per_night' => 'per night',
                'per_person' => 'per person',
                'per_activity' => 'per activity',
                'trip_total' => 'for the whole trip',
                default => 'noted',
            };
        }

        return array_values(array_unique($noted));
    }

    /** @param list<string> $items */
    private function joinList(array $items): string
    {
        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }
        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    /**
     * An equivalent task already in this conversation, so repeating a request
     * refreshes it instead of consuming another saved slot. A different
     * destination, a different named entity or explicitly different dates are
     * a different trip and stay separate.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>|null
     */
    private function reuse(array $state, array $task): ?array
    {
        foreach ($state['tasks'] as $candidate) {
            if (($candidate['type'] ?? null) !== $task['type'] || ($candidate['status'] ?? null) === 'cancelled') {
                continue;
            }
            if ((int) ($candidate['constraints']['destination_id'] ?? 0) !== (int) ($task['constraints']['destination_id'] ?? 0)) {
                continue;
            }
            foreach (['hotel_name', 'room_name', 'activity_name', 'package_name'] as $key) {
                if (($candidate['constraints'][$key] ?? null) !== ($task['constraints'][$key] ?? null)) {
                    continue 2;
                }
            }
            $newDates = ! empty($task['constraints']['check_in_date'])
                && ! empty($candidate['constraints']['check_in_date'])
                && $task['constraints']['check_in_date'] !== $candidate['constraints']['check_in_date'];
            if ($newDates) {
                continue;
            }

            return [
                ...$task,
                'persistent_id' => $candidate['persistent_id'],
                'scope_id' => $candidate['scope_id'],
                'status' => 'collecting',
                'slots' => is_array($candidate['slots'] ?? null) ? $candidate['slots'] : [],
                'budget_basis' => $task['budget_basis'] ?? $candidate['budget_basis'] ?? 'unknown',
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function suspended(array $state): array
    {
        foreach ($state['tasks'] as &$task) {
            if ($task['status'] === 'collecting') {
                $task['status'] = 'suspended';
            }
        }
        unset($task);
        $state['active_question'] = null;

        return $state;
    }

    public function suspendForSupport(ChatSession $session): void
    {
        $state = $this->suspended($this->state($session));
        $state['revision'] = (int) ($state['revision'] ?? 0) + 1;
        $metadata = $session->metadata ?? [];
        $metadata['conversation_v2']['dialogue'] = $state;
        $session->update(['metadata' => $metadata]);
    }

    /** @return array<string, mixed> */
    private function statusTask(string $message): array
    {
        return ['id' => 'task-1', 'type' => 'DIALOGUE_STATUS', 'query' => '', 'constraints' => [], 'budget_basis' => 'unknown', 'dialogue_message' => $message];
    }

    /** @param array<string, mixed> $plan
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function finish(array $plan, array $payload): array
    {
        $state = $plan['dialogue'];
        $candidates = [];
        $priority = ['destination' => 0, 'dates' => 1, 'days' => 2, 'pax' => 3, 'nights' => 4, 'budget_scope' => 5, 'budget_basis' => 6, 'budget_period' => 7, 'budget_coverage' => 8, 'budget_allocation' => 9];
        foreach ($payload['sections'] as &$section) {
            if (! isset($section['persistent_id'])) {
                continue;
            }
            $task = $plan['tasks'][array_search($section['id'], array_column($plan['tasks'], 'id'), true)];
            $field = ($section['outcome']['status'] ?? '') === RetrievalOutcome::NeedsClarification ? ($task['requirement'] ?? match ($section['outcome']['reason'] ?? '') {
                'missing_destination' => 'destination', 'missing_dates' => 'dates', 'missing_budget_basis' => 'budget_basis', 'joint_budget_allocation' => 'budget_allocation', default => null,
            }) : null;
            if (! empty($task['combined_budget_pending'])) {
                $field = null;
            }
            // A shared or unresolved budget is only worth disambiguating while
            // its basis is still unknown, and never ahead of a more specific
            // detail the task is already waiting on (duration, pax, dates).
            if (isset($task['budget_scope'])
                && ($task['budget_basis'] ?? 'unknown') === 'unknown'
                && ($task['requirement'] ?? null) === null
                && ! in_array($section['outcome']['status'] ?? '', [RetrievalOutcome::NoMatch, RetrievalOutcome::TemporarilyUnavailable], true)) {
                $field = 'budget_scope';
            }
            $task['constraints'] = $section['constraints'];
            $task['status'] = ($section['outcome']['status'] ?? '') === RetrievalOutcome::Deferred ? 'suspended' : ($field !== null ? 'collecting' : 'answered');
            if ($field !== null && $task['status'] !== 'suspended') {
                $scope = $task['scope_id'].':'.$field;
                $candidates[$scope]['field'] = $field;
                $candidates[$scope]['task_ids'][] = $task['persistent_id'];
                $candidates[$scope]['destination'] = $task['constraints']['destination_name'] ?? null;
                $candidates[$scope]['attempts'] = max($candidates[$scope]['attempts'] ?? 0, $task['invalid_attempts'] ?? 0);
                $candidates[$scope]['acknowledged'] = array_values(array_unique(array_merge($candidates[$scope]['acknowledged'] ?? [], $task['acknowledged'] ?? [])));
                if (! empty($section['data']['budget_unverified'])) {
                    $section['answer'] = explode("\n\n", $section['answer'])[0];
                } elseif (empty($section['card_refs'])) {
                    $section['answer'] = 'Still waiting on '.self::fieldLabel($field).' for this request.';
                }
            }
            if (! empty($task['discovery_only'])) {
                $section['answer'] = 'Stay dates are undecided, so these are general options. Dated availability has not been checked. '.$section['answer'];
            }
            $stored = array_intersect_key($task, array_flip(['persistent_id', 'scope_id', 'type', 'query', 'constraints', 'status', 'slots', 'budget_basis', 'budget_period', 'budget_coverage', 'budget_scope', 'budget_amount', 'joint_budget', 'entity', 'reference']));
            $state['tasks'] = array_values(array_filter($state['tasks'], fn (array $t): bool => $t['persistent_id'] !== $task['persistent_id']));
            $state['tasks'][] = $stored;
        }
        unset($section);
        uasort($candidates, fn (array $a, array $b): int => ($priority[$a['field']] ?? 10) <=> ($priority[$b['field']] ?? 10));
        $next = $candidates === [] ? null : reset($candidates);
        $question = null;
        if ($next !== null) {
            $question = ['id' => (string) Str::uuid(), 'revision' => $state['revision'], 'field' => $next['field'], 'task_ids' => $next['task_ids'], 'required' => true, 'attempts' => $next['attempts'], 'text' => $this->questionText($next, $plan), 'options' => []];
            if (in_array($next['field'], ['dates', 'budget_scope', 'budget_basis', 'budget_period', 'budget_coverage'], true)) {
                $question['options'][] = ['label' => $next['field'] === 'dates' ? 'Dates undecided' : 'No budget preference', 'value' => $next['field'] === 'dates' ? 'Dates undecided' : 'No budget preference'];
            }
            // The control is derived from question state and verified catalog
            // data only, never from generated text, and is additive: a client
            // that ignores it still has the prompt and the skip action.
            $question['control'] = $this->questionControl($next, $plan);
        }
        $state['active_question'] = $question;
        $overflowNotice = count($state['tasks']) > 8;
        $state['tasks'] = array_slice($state['tasks'], -8);
        $suspendedScopes = [];
        foreach (array_reverse($state['tasks'], true) as $i => $task) {
            if ($task['status'] === 'suspended') {
                if (! in_array($task['scope_id'], $suspendedScopes, true) && count($suspendedScopes) >= 2) {
                    $state['tasks'][$i]['status'] = 'cancelled';
                    $overflowNotice = true;
                } else {
                    $suspendedScopes[] = $task['scope_id'];
                    $suspendedScopes = array_unique($suspendedScopes);
                }
            }
        }
        $state['trip_scopes'] = [];
        foreach ($state['tasks'] as $task) {
            $state['trip_scopes'][$task['scope_id']] = array_intersect_key($task['constraints'], array_flip(['destination_id', 'destination_name', 'pax', 'days', 'nights', 'check_in_date', 'check_out_date']));
        }
        $payload['clarification'] = $question;
        $payload['trip_summary'] = $this->tripSummary($state);
        $payload['trip_details_form'] = $this->tripDetails->build($state);
        $state['trip_details_form'] = $payload['trip_details_form'];
        $payload['dialogue_revision'] = $state['revision'];
        $payload['_dialogue_state'] = $state;
        $texts = [];
        foreach ($payload['sections'] as $section) {
            $texts[] = '### '.$section['heading'].(! empty($section['constraints']['destination_name']) ? ' — '.$section['constraints']['destination_name'] : '')."\n\n".$section['answer'];
        }
        if ($overflowNotice) {
            $payload['dialogue_notice'] = 'This conversation has reached its saved-task limit. Please repeat any older request you want to continue.';
            $texts[] = $payload['dialogue_notice'];
        }
        if ($question !== null) {
            unset($payload['section_footer']);
            $texts[] = $question['text'];
            $payload['suggested_actions'] = array_values(array_filter($payload['suggested_actions'] ?? [], fn (array $a): bool => ($a['handoff'] ?? false) === true));
            foreach ($question['options'] as $i => $option) {
                $payload['suggested_actions'][] = ['id' => 'answer-'.$question['id'].'-'.$i, 'label' => $option['label'], 'prompt' => $option['value'], 'question_id' => $question['id'], 'question_revision' => $question['revision']];
            }
        } elseif (! empty($payload['section_footer'])) {
            $texts[] = $payload['section_footer'];
        }
        $payload['reply'] = implode("\n\n", $texts);

        return $payload;
    }

    /** @param array<string, mixed> $next
     * @param  array<string, mixed>  $plan
     */
    private function questionText(array $next, array $plan): string
    {
        $exampleIn = now()->addWeeks(2)->toDateString();
        $exampleOut = now()->addWeeks(2)->addDays(3)->toDateString();
        $taglish = (bool) preg_match('/\b(?:sa|kami|ako|gusto|paki|dalawa|apat|araw|gabi)\b/iu', implode(' ', array_column($plan['tasks'], 'query')));
        $text = match ($next['field']) {
            'destination' => $taglish ? 'Saan ninyo gustong pumunta?' : 'Which destination should I use?',
            'days' => $taglish ? 'Ilang araw ang trip ninyo?' : 'How many days is your trip?',
            'pax' => $taglish ? 'Ilan kayong travelers, kasama ang mga bata?' : 'How many travelers are going, including children?',
            'nights' => 'How many hotel nights should I include in the quoted total?',
            'dates' => 'What check-in and check-out dates should I use? For example, '.$exampleIn.' to '.$exampleOut.'.',
            'budget_scope' => 'Does your budget cover the whole trip, the stay per night, or activities per person?',
            'budget_allocation' => 'How much should I allocate to each part, or would you like one combined itinerary?',
            'budget_period' => 'Is that amount per night, or for the whole stay?',
            'budget_coverage' => 'Does that nightly amount cover the hotel only, or everything including activities?',
            default => 'Does this budget apply per night, per person, or to the whole trip?',
        };
        if (($next['attempts'] ?? 0) > 0) {
            $text = 'I could not confirm that detail. '.$text;
        } elseif (! empty($next['acknowledged'])) {
            // Volunteered detail is confirmed before the outstanding question
            // so the turn never reads as if the answer was ignored.
            $text = 'Got it — '.$this->joinList($next['acknowledged']).'. '.$text;
        }
        if (! empty($next['destination'])) {
            $text = 'For '.$next['destination'].': '.$text;
        }

        return $text;
    }
}
