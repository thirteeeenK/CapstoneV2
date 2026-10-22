<?php

use App\Services\Chat\RetrievalOutcome;

test('an unanswered task names why it produced no answer', function (string $status, string $reason, ?string $kind) {
    expect(RetrievalOutcome::recoveryKind(RetrievalOutcome::make($status, $reason)))->toBe($kind);
})->with([
    [RetrievalOutcome::Matched, 'verified_result_found', null],
    [RetrievalOutcome::TemporarilyUnavailable, 'retrieval_failed', 'provider_failure'],
    [RetrievalOutcome::NeedsClarification, 'missing_destination', 'ambiguous_wording'],
    [RetrievalOutcome::NoMatch, 'hard_budget_no_match', 'impossible_constraints'],
    [RetrievalOutcome::NoMatch, 'stale_reference', 'unavailable_inventory'],
    [RetrievalOutcome::NoMatch, 'no_reviewed_guidance_for_topic', 'absent_data'],
    [RetrievalOutcome::Unsupported, 'data_source_not_supported', 'absent_data'],
    [RetrievalOutcome::NoMatch, 'no_results', 'no_matching_inventory'],
]);
