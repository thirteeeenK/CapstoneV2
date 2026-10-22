<?php

namespace App\Services\Chat;

final class RetrievalOutcome
{
    public const Matched = 'matched';

    public const NeedsClarification = 'needs_clarification';

    public const NoMatch = 'no_match';

    public const TemporarilyUnavailable = 'temporarily_unavailable';

    public const Unsupported = 'unsupported';

    public const Deferred = 'deferred';

    public const Partial = 'partial';

    /**
     * Why a task produced no substantive answer, so an empty result reads as
     * one of: unavailable inventory, impossible constraints, absent data,
     * ambiguous wording, or a temporary provider failure.
     *
     * @param  array<string, mixed>  $outcome
     */
    public static function recoveryKind(array $outcome): ?string
    {
        $reason = (string) ($outcome['reason'] ?? '');

        return match ($outcome['status'] ?? null) {
            self::Matched, self::Partial, self::Deferred, null => null,
            self::TemporarilyUnavailable => 'provider_failure',
            self::NeedsClarification => 'ambiguous_wording',
            self::Unsupported => 'absent_data',
            default => match (true) {
                in_array($reason, ['hard_budget_no_match', 'excluded_features', 'no_cheaper_option'], true) => 'impossible_constraints',
                in_array($reason, ['stale_reference', 'not_available', 'unavailable'], true) => 'unavailable_inventory',
                str_starts_with($reason, 'no_reviewed_guidance') || in_array($reason, ['unsupported_destination', 'data_source_not_supported'], true) => 'absent_data',
                default => 'no_matching_inventory',
            },
        };
    }

    public static function make(string $status, string $reason, array $relaxedConstraints = [], array $suggestedActions = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'relaxed_constraints' => array_values($relaxedConstraints), 'suggested_actions' => array_values($suggestedActions)];
    }
}
