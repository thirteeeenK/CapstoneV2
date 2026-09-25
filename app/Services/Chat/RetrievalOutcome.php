<?php

namespace App\Services\Chat;

final class RetrievalOutcome
{
    public const Matched = 'matched';

    public const NeedsClarification = 'needs_clarification';

    public const NoMatch = 'no_match';

    public const TemporarilyUnavailable = 'temporarily_unavailable';

    public static function make(string $status, string $reason, array $relaxedConstraints = [], array $suggestedActions = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'relaxed_constraints' => array_values($relaxedConstraints), 'suggested_actions' => array_values($suggestedActions)];
    }
}
