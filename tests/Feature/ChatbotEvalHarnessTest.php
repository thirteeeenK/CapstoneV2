<?php

use App\Models\ChatSession;
use App\Models\User;
use Tests\Support\ChatEvalMetrics;

test('guided dialogue accuracy counts every checked turn and reports absent data honestly', function () {
    expect(ChatEvalMetrics::dialogueAccuracy([['correct' => true], ['correct' => false], ['correct' => true], ['correct' => false]]))->toBe(0.5);
    expect(ChatEvalMetrics::dialogueAccuracy([]))->toBeNull();
});

test('guided evaluation can run twice without retaining fixture accounts or sessions', function () {
    $userCount = User::withTrashed()->count();
    $sessionCount = ChatSession::count();

    $this->artisan('chatbot:eval', ['--guided' => true])->assertExitCode(0);
    $this->artisan('chatbot:eval', ['--guided' => true])->assertExitCode(0);

    expect(User::withTrashed()->count())->toBe($userCount);
    expect(ChatSession::count())->toBe($sessionCount);
});

test('multi task evaluation separates coverage from intent correctness and catalog identity', function () {
    $rows = [
        ['expect_intents' => ['HOTEL_SEARCH', 'ACTIVITY_SEARCH'], 'intents' => ['ACTIVITY_SEARCH', 'HOTEL_SEARCH'], 'accounted_intents' => ['HOTEL_SEARCH', 'ACTIVITY_SEARCH']],
        ['expect_intents' => ['HOTEL_SEARCH', 'WEATHER_QUERY'], 'intents' => ['HOTEL_SEARCH'], 'accounted_intents' => ['HOTEL_SEARCH']],
    ];
    expect(ChatEvalMetrics::intentSetAccuracy($rows))->toBe(0.5)
        ->and(ChatEvalMetrics::taskCoverage($rows))->toBe(0.75)
        ->and(ChatEvalMetrics::typedResultIds([['card_refs' => [['type' => 'hotel', 'id' => 1], ['type' => 'activity', 'id' => 1]]]]))->toBe(['hotel:1', 'activity:1']);
});

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
        ['constraint_compliant' => true, 'claims_grounded' => true, 'expect_abstention' => true, 'abstained' => true, 'scope_consistent' => true],
        ['constraint_compliant' => false, 'claims_grounded' => false, 'expect_abstention' => false, 'abstained' => true, 'scope_consistent' => false],
    ];

    expect(ChatEvalMetrics::constraintViolationRate($results))->toBe(0.5)
        ->and(ChatEvalMetrics::unsupportedClaimRate($results))->toBe(0.5)
        ->and(ChatEvalMetrics::validAbstentionRate($results))->toBe(0.5)
        ->and(ChatEvalMetrics::multiTurnConsistencyRate($results))->toBe(0.5);
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
