<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;

class ConversationManager
{
    /**
     * Most recent session belonging to the user that has at least one message.
     * Used so authenticated history survives logout+login even when localStorage token is stale.
     */
    public function latestForUser(User $user): ?ChatSession
    {
        return ChatSession::where('user_id', $user->id)
            ->whereHas('messages')
            ->latest('updated_at')
            ->first()
            ?? ChatSession::where('user_id', $user->id)->latest('updated_at')->first();
    }

    public function resolveSession(?string $token, ?User $user): ChatSession
    {
        $session = null;

        if ($token) {
            $session = ChatSession::where('session_token', $token)->first();
        }

        // Isolation: guest or different user probing someone else's authed session.
        if ($session && $session->user_id !== null && (! $user || (int) $session->user_id !== (int) $user->id)) {
            if (! $user) {
                // Guest probing authed token — give them a fresh guest session (security) without leaking.
                return ChatSession::create([
                    'session_token' => ChatSession::generateToken(),
                    'user_id' => null,
                ]);
            }

            // Authed user probing another user's token — return own latest instead of leaking.
            return $this->latestForUser($user)
                ?? ChatSession::create([
                    'session_token' => ChatSession::generateToken(),
                    'user_id' => $user->id,
                ]);
        }

        if (! $session) {
            if ($user) {
                // No token or token not found — recover latest account-bound session so history survives logout+login.
                return $this->latestForUser($user)
                    ?? ChatSession::create([
                        'session_token' => ChatSession::generateToken(),
                        'user_id' => $user->id,
                    ]);
            }

            return ChatSession::create([
                'session_token' => ChatSession::generateToken(),
                'user_id' => null,
            ]);
        }

        // Session found and accessible.
        if ($user && $session->isGuest()) {
            // If this guest session is an empty probe but the user already has a real history, prefer the real history.
            $latest = $this->latestForUser($user);
            if ($latest && $latest->id !== $session->id && $session->messages()->count() === 0) {
                return $latest;
            }
            $session->claimFor($user);
        }

        return $session;
    }

    public function findOrCreateGuest(string $token): ChatSession
    {
        return ChatSession::firstOrCreate(
            ['session_token' => $token],
            ['user_id' => null]
        );
    }

    public function history(ChatSession $session, int $turns = 6, bool $excludeLatestUserMessage = false): array
    {
        $messages = $session->messages()
            ->latest('id')
            ->limit(($turns * 2) + ($excludeLatestUserMessage ? 1 : 0))
            ->get()
            ->reverse()
            ->values();

        if ($excludeLatestUserMessage && $messages->last()?->sender === 'user') {
            $messages->pop();
        }

        $contents = [];
        foreach ($messages as $msg) {
            if ($msg->sender === 'admin') {
                $contents[] = [
                    'role' => 'model',
                    'parts' => [['text' => 'Support Agent (human): '.$msg->message]],
                ];
            } else {
                $role = $msg->sender === 'user' ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg->message]],
                ];
            }
        }

        return $contents;
    }

    public function persist(ChatSession $session, string $sender, string $message, ?array $contextData = null, ?array $dialogueState = null): ChatMessage
    {
        $chatMessage = ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender' => $sender,
            'message' => $message,
            'context_data' => $contextData,
        ]);

        if ($sender === 'bot' && $contextData) {
            $this->rememberActiveSearch($session, $contextData);
            $session->refresh();
            $this->rememberRetrievalState($session, $contextData);
            $session->refresh();
            $this->rememberTaskState($session, $contextData);
            if ($dialogueState !== null) {
                $metadata = $session->metadata ?? [];
                $metadata['conversation_v2']['dialogue'] = $dialogueState;
                $metadata['conversation_v2']['pending'] = [];
                $session->update(['metadata' => $metadata]);
            }
        }

        return $chatMessage;
    }

    /**
     * The structured frame of the latest retrieval turn, or [] when the
     * session has no stored frame yet.
     *
     * @return array<string, mixed>
     */
    public function currentFrame(ChatSession $session): array
    {
        $frame = $session->metadata['retrieval_state'] ?? [];

        return is_array($frame) ? $frame : [];
    }

    /** @return array<string, mixed> */
    public function activeSearch(ChatSession $session): array
    {
        $state = $session->metadata['active_search_state'] ?? [];

        return is_array($state) ? $state : [];
    }

    /** @return list<array<string, mixed>> */
    public function resultGroups(ChatSession $session): array
    {
        $groups = $session->metadata['conversation_v2']['groups'] ?? [];
        if (is_array($groups) && $groups !== []) {
            return array_values($groups);
        }
        $frame = $this->currentFrame($session);
        if (empty($frame['result_ids'])) {
            return [];
        }

        return [['type' => $frame['type'], 'intent' => $frame['intent'] ?? strtoupper($frame['type']).'_SEARCH', 'result_ids' => $frame['result_ids'], 'constraints' => $frame, 'source_turn' => $frame['source_turn'] ?? null]];
    }

    /** @return list<array<string, mixed>> */
    public function pendingTasks(ChatSession $session): array
    {
        $pending = $session->metadata['conversation_v2']['pending'] ?? [];

        return is_array($pending) ? array_values($pending) : [];
    }

    /** @param array<string, mixed> $data */
    private function rememberTaskState(ChatSession $session, array $data): void
    {
        $metadata = $session->metadata ?? [];
        $state = $metadata['conversation_v2'] ?? ['version' => 2, 'groups' => [], 'pending' => []];
        $groups = empty($data['reset_destination']) ? ($state['groups'] ?? []) : [];
        $pending = empty($data['reset_destination']) ? ($state['pending'] ?? []) : [];
        $sections = $data['sections'] ?? [[
            'id' => 'legacy', 'type' => $data['trace']['intent'] ?? '',
            'constraints' => $data['trace']['constraints'] ?? [],
            'outcome' => $data['retrieval_outcome'] ?? [], 'data' => $data,
        ]];
        $catalogs = ['retrieved_rooms' => 'room', 'retrieved_hotels' => 'hotel', 'retrieved_activities' => 'activity', 'retrieved_packages' => 'package', 'retrieved_addons' => 'addon'];
        unset($state['last_action']);
        foreach ($sections as $section) {
            if (($section['outcome']['reason'] ?? null) === 'action_not_supported') {
                $state['last_action'] = ['operation' => $section['operation'] ?? null];
            }
            $constraints = array_filter($section['constraints'] ?? [], static fn (mixed $v): bool => $v !== null && $v !== []);
            $scope = ($section['type'] ?? '').':'.($constraints['destination_id'] ?? 'any');
            if (($section['outcome']['status'] ?? null) === RetrievalOutcome::NeedsClarification) {
                $pending[$scope] = array_intersect_key($section, array_flip(['id', 'type', 'query', 'constraints', 'outcome']));
            } elseif (in_array($section['outcome']['status'] ?? null, [RetrievalOutcome::Matched, RetrievalOutcome::NoMatch], true)) {
                unset($pending[$scope]);
                foreach ($pending as $pendingKey => $pendingTask) {
                    if (($pendingTask['type'] ?? null) === ($section['type'] ?? null) && empty($pendingTask['constraints']['destination_id'])) {
                        unset($pending[$pendingKey]);
                    }
                }
            }
            foreach ($catalogs as $key => $type) {
                $cards = $section['data'][$key] ?? [];
                if ($cards === []) {
                    continue;
                }
                $first = $cards[0];
                $groupKey = $type.':'.($constraints['destination_id'] ?? $first['destination_id'] ?? 'any');
                $groupIntent = $section['type'] ?? '';
                if ($groupIntent === '' || ! isset($data['sections'])) {
                    $groupIntent = strtoupper($type === 'addon' ? 'addon' : $type).'_SEARCH';
                }
                $groups[$groupKey] = [
                    'task_id' => $section['id'], 'type' => $type, 'intent' => $groupIntent,
                    // The request that produced the group, so a later
                    // correction can re-run it instead of searching its own words.
                    'query' => mb_substr((string) ($section['query'] ?? $session->messages()->where('sender', 'user')->latest('id')->value('message') ?? ''), 0, 300),
                    'result_ids' => array_values(array_map('intval', array_column($cards, 'id'))),
                    'constraints' => array_replace($constraints, array_filter([
                        'destination_id' => $first['destination_id'] ?? null,
                        'destination_name' => $first['destination'] ?? null,
                    ], static fn (mixed $v): bool => $v !== null)),
                    'source_turn' => $data['trace']['trace_id'] ?? null, 'retrieved_at' => now()->toIso8601String(),
                ];
            }
        }
        $state['groups'] = array_slice($groups, -12, null, true);
        $state['pending'] = array_slice($pending, -8, null, true);
        $state['trip_facts'] = $this->updatedTripFacts(is_array($state['trip_facts'] ?? null) ? $state['trip_facts'] : [], $sections);
        $metadata['conversation_v2'] = $state;
        $session->update(['metadata' => $metadata]);
    }

    /**
     * Accepted trip facts for this conversation, kept beyond the recent prose
     * window. User context only: never proof of current prices or
     * availability, and it holds no model text or account details.
     *
     * @return array<string, mixed>
     */
    public function tripFacts(ChatSession $session): array
    {
        $facts = $session->metadata['conversation_v2']['trip_facts'] ?? [];

        return is_array($facts) ? $facts : [];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function updatedTripFacts(array $facts, array $sections): array
    {
        $fields = ['destination_id', 'destination_name', 'pax', 'children', 'check_in_date', 'check_out_date', 'nights', 'max_price', 'budget_mode'];
        foreach ($sections as $section) {
            if (! in_array($section['outcome']['status'] ?? null, [RetrievalOutcome::Matched, RetrievalOutcome::Partial, RetrievalOutcome::NoMatch], true)) {
                continue;
            }
            $constraints = $section['constraints'] ?? [];
            if (! empty($constraints['destination_id']) && ! empty($facts['destination_id']) && (int) $constraints['destination_id'] !== (int) $facts['destination_id']) {
                // Selections belong to one destination; shared preferences
                // (party, dates, budget, exclusions) carry over.
                $facts['selected'] = [];
            }
            foreach ($fields as $field) {
                $value = $constraints[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if (array_key_exists($field, $facts) && $facts[$field] !== null && $facts[$field] != $value && ! in_array($field, ['destination_name', 'budget_mode'], true)) {
                    $facts['corrections'][] = ['field' => $field, 'from' => $facts[$field], 'to' => $value, 'at' => now()->toIso8601String()];
                }
                $facts[$field] = $value;
            }
            if (($section['budget_basis'] ?? 'unknown') !== 'unknown' && ! empty($constraints['max_price'])) {
                $facts['budget_basis'] = $section['budget_basis'];
            }
            foreach (['excluded_terms', 'excluded_names'] as $field) {
                if (! empty($constraints[$field])) {
                    $facts[$field] = array_values(array_unique([...($facts[$field] ?? []), ...$constraints[$field]]));
                }
            }
            if (! empty($section['entity']['type']) && ! empty($section['entity']['id'])) {
                $facts['selected'][$section['entity']['type']] = (int) $section['entity']['id'];
            }
        }
        if (isset($facts['corrections'])) {
            $facts['corrections'] = array_slice($facts['corrections'], -5);
        }
        $facts['updated_at'] = now()->toIso8601String();

        return $facts;
    }

    protected function rememberActiveSearch(ChatSession $session, array $contextData): void
    {
        $trace = $contextData['trace'] ?? [];
        $constraints = $trace['constraints'] ?? [];
        if (! is_array($constraints)) {
            return;
        }

        $metadata = $session->metadata ?? [];
        $metadata['active_search_state'] = array_filter([
            'intent' => $trace['intent'] ?? null,
            'destination_id' => $constraints['destination_id'] ?? null,
            'destination_name' => $constraints['destination_name'] ?? null,
            'hotel_id' => $constraints['hotel_id'] ?? null,
            'room_id' => $constraints['room_id'] ?? null,
            'pax' => $constraints['pax'] ?? null,
            'max_price' => $constraints['max_price'] ?? null,
            'check_in_date' => $constraints['check_in_date'] ?? null,
            'check_out_date' => $constraints['check_out_date'] ?? null,
            'outcome' => $contextData['retrieval_outcome']['status'] ?? ($this->hasRetrievalResults($contextData) ? RetrievalOutcome::Matched : null),
            'reason' => $contextData['retrieval_outcome']['reason'] ?? null,
            'source_turn' => $trace['trace_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        $session->update(['metadata' => $metadata]);
    }

    protected function hasRetrievalResults(array $contextData): bool
    {
        foreach (['retrieved_rooms', 'retrieved_hotels', 'retrieved_activities', 'retrieved_packages', 'retrieved_addons'] as $key) {
            if (! empty($contextData[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store the compact, structured parts of the latest retrieval turn in
     * displayed order. This avoids repeatedly inferring conversational scope
     * from rendered prose.
     *
     * @param  array<string, mixed>  $contextData
     */
    protected function rememberRetrievalState(ChatSession $session, array $contextData): void
    {
        $resultGroups = [
            'retrieved_rooms' => 'room',
            'retrieved_hotels' => 'hotel',
            'retrieved_activities' => 'activity',
            'retrieved_packages' => 'package',
            'retrieved_addons' => 'addon',
        ];

        $traceConstraints = $contextData['trace']['constraints'] ?? [];

        foreach ($resultGroups as $key => $type) {
            $results = $contextData[$key] ?? null;
            if (! is_array($results) || $results === []) {
                continue;
            }
            $first = $results[0];
            if (! is_array($first)) {
                continue;
            }

            $ids = [];
            foreach ($results as $row) {
                if (is_array($row) && isset($row['id'])) {
                    $ids[] = (int) $row['id'];
                }
            }

            $metadata = $session->metadata ?? [];
            $metadata['retrieval_state'] = array_filter([
                'type' => $type,
                'entity_id' => isset($first['id']) ? (int) $first['id'] : null,
                'result_ids' => $ids !== [] ? $ids : null,
                'hotel_id' => isset($first['hotel_id']) ? (int) $first['hotel_id'] : null,
                'destination_id' => isset($first['destination_id']) ? (int) $first['destination_id'] : null,
                'destination_name' => $first['destination'] ?? null,
                'check_in_date' => $first['check_in_date'] ?? null,
                'check_out_date' => $first['check_out_date'] ?? null,
                'pax' => isset($first['pax']) ? (int) $first['pax'] : null,
                'max_price' => isset($traceConstraints['max_price']) ? (int) $traceConstraints['max_price'] : null,
                'intent' => $contextData['trace']['intent'] ?? $contextData['intent'] ?? null,
                'explicit_constraints' => $contextData['explicit_constraints'] ?? [],
                'source_turn' => $contextData['trace']['trace_id'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
            $session->update(['metadata' => $metadata]);

            return;
        }
    }

    public function summarizeHistory(ChatSession $session): ?string
    {
        $count = $session->messages()->count();
        if ($count < 20) {
            return null;
        }

        $oldMessages = $session->messages()
            ->where('sender', 'user')
            ->oldest('created_at')
            ->limit(max(1, $count - 12))
            ->pluck('message');

        return 'Earlier in the conversation, the user asked: '.$oldMessages->implode('; ').'.';
    }
}
