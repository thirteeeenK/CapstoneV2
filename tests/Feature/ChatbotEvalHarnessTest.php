<?php

use App\Models\User;
use App\Services\Chat\ChatEvalHarness;
use App\Services\Chat\ChatEvalMetrics;

test('eval metrics match hand-computed values on synthetic rows', function () {
    $results = [
        [
            'expect_intent' => 'HOTEL_SEARCH',
            'expect_destination' => 'Boracay',
            'expect_ids' => [12, 15],
            'forbid_ids' => [99],
            'intent' => 'HOTEL_SEARCH',
            'retrieved_ids' => [12, 15, 99],
            'destinations' => ['Boracay'],
            'latency_ms' => 100.0,
        ],
        [
            'expect_intent' => 'ROOM_SEARCH',
            'expect_destination' => 'Boracay',
            'expect_ids' => [7],
            'forbid_ids' => [],
            'intent' => 'HOTEL_SEARCH',
            'retrieved_ids' => [3, 7],
            'destinations' => ['Boracay', 'Cebu'],
            'latency_ms' => 300.0,
        ],
        [
            'expect_intent' => null,
            'expect_destination' => null,
            'expect_ids' => [],
            'forbid_ids' => [],
            'intent' => 'GENERAL_TALK',
            'retrieved_ids' => [],
            'destinations' => [],
            'latency_ms' => 200.0,
        ],
    ];

    expect(ChatEvalMetrics::intentAccuracy($results))->toBe(0.5)
        ->and(ChatEvalMetrics::recallAt($results, 3))->toBe(1.0)
        ->and(ChatEvalMetrics::recallAt($results, 1))->toBe(0.5)
        // Ranks 1 and 2 → (1 + 1/2) / 2.
        ->and(ChatEvalMetrics::mrr($results))->toBe(0.75)
        ->and(ChatEvalMetrics::destinationMatchRate($results))->toBe(1.0)
        ->and(ChatEvalMetrics::wrongDestinationRate($results))->toBe(0.5)
        ->and(ChatEvalMetrics::noResultRate($results))->toBe(1 / 3)
        ->and(ChatEvalMetrics::latencyP50($results))->toBe(200.0)
        ->and(ChatEvalMetrics::latencyP95($results))->toBe(300.0);
});

test('eval metrics measure grounding constraints abstention and multi turn consistency', function () {
    $results = [
        // Multi-turn follow-up that stayed inside the scoped destination and entity.
        [
            'constraint_compliant' => true, 'claims_grounded' => true,
            'expect_abstention' => true, 'abstained' => true,
            'is_multi_turn' => true, 'expect_destination' => 'Boracay',
            'expect_ids' => [12], 'retrieved_ids' => [12], 'destinations' => ['Boracay'],
        ],
        // Multi-turn follow-up that leaked to another destination and missed the entity.
        [
            'constraint_compliant' => false, 'claims_grounded' => false,
            'expect_abstention' => false, 'abstained' => true,
            'is_multi_turn' => true, 'expect_destination' => 'Boracay',
            'expect_ids' => [7], 'retrieved_ids' => [3], 'destinations' => ['Cebu'],
        ],
    ];

    expect(ChatEvalMetrics::constraintViolationRate($results))->toBe(0.5)
        ->and(ChatEvalMetrics::unsupportedClaimRate($results))->toBe(0.5)
        ->and(ChatEvalMetrics::validAbstentionRate($results))->toBe(0.5)
        ->and(ChatEvalMetrics::multiTurnConsistencyRate($results))->toBe(0.5);
});

test('multi turn consistency is derived from retrieval and ignores single turn rows', function () {
    $results = [
        [
            'is_multi_turn' => true, 'expect_destination' => 'Boracay',
            'expect_ids' => [12], 'retrieved_ids' => [12], 'destinations' => ['Boracay'],
        ],
        // Single-turn row: excluded from the denominator even though it retrieved nothing.
        [
            'is_multi_turn' => false, 'expect_destination' => 'Boracay',
            'expect_ids' => [3], 'retrieved_ids' => [], 'destinations' => [],
        ],
    ];

    expect(ChatEvalMetrics::multiTurnConsistencyRate($results))->toBe(1.0)
        ->and(ChatEvalMetrics::multiTurnConsistencyRate([$results[1]]))->toBeNull()
        // A fixture-supplied flag must not be able to stand in for a real measurement.
        ->and(ChatEvalMetrics::multiTurnConsistencyRate([
            ['is_multi_turn' => true, 'expect_destination' => 'Boracay', 'expect_ids' => [3],
                'retrieved_ids' => [], 'destinations' => [], 'scope_consistent' => true],
        ]))->toBe(0.0);
});

test('eval metrics return null when no case carries the expectation', function () {
    $results = [[
        'expect_intent' => null,
        'expect_destination' => null,
        'expect_ids' => [],
        'forbid_ids' => [],
        'intent' => 'GENERAL_TALK',
        'retrieved_ids' => [],
        'destinations' => [],
        'latency_ms' => null,
    ]];

    expect(ChatEvalMetrics::intentAccuracy($results))->toBeNull()
        ->and(ChatEvalMetrics::recallAt($results, 3))->toBeNull()
        ->and(ChatEvalMetrics::mrr($results))->toBeNull()
        ->and(ChatEvalMetrics::destinationMatchRate($results))->toBeNull()
        ->and(ChatEvalMetrics::wrongDestinationRate($results))->toBeNull()
        ->and(ChatEvalMetrics::latencyP50($results))->toBeNull()
        ->and(ChatEvalMetrics::noResultRate($results))->toBe(1.0);
});

test('the eval harness creates its personalized account only when a personalized case runs', function () {
    mockGemini(unitVector(0), 'Eval reply.');
    $harness = new ChatEvalHarness;

    try {
        $harness->seed();

        expect(User::where('email', 'eval-harness@example.com')->exists())->toBeFalse();

        $harness->ask([
            'auth' => 'personalized',
            'query' => 'show me hotels in Eval Boracay',
        ]);

        expect(User::where('email', 'eval-harness@example.com')->exists())->toBeTrue();
    } finally {
        $harness->cleanup();
    }
});

test('the full evaluation fixture retains fifty cases and limits the judge to retrieval-grounded cases', function () {
    $cases = json_decode((string) file_get_contents(base_path('tests/Fixtures/chatbot_eval_v1.json')), true);
    $judgeEligible = array_filter($cases, fn (array $case): bool => ($case['judge_eligible'] ?? true) !== false);

    expect($cases)->toHaveCount(50)
        ->and($judgeEligible)->toHaveCount(33);
});
