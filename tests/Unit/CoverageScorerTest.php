<?php

use Tests\Support\CoverageScorer;

test('observe folds sections into topic labels with typed card ids', function () {
    $observed = CoverageScorer::observe([
        'reply' => 'Here you go.',
        'sections' => [
            ['type' => 'HOTEL_SEARCH', 'outcome' => ['status' => 'matched'], 'constraints' => ['pax' => 2]],
            ['type' => 'UNSUPPORTED_INFORMATION', 'outcome' => ['status' => 'unsupported'], 'constraints' => []],
        ],
        'retrieved_hotels' => [['id' => 4, 'destination' => 'Marikit'], ['id' => 9, 'destination' => ['name' => 'Marikit']]],
    ]);

    expect($observed['tasks'])->toBe(['stay' => ['answer'], 'info' => ['abstain']])
        ->and($observed['outcome'])->toBe('answer')
        ->and($observed['typed'])->toBe(['hotel:4', 'hotel:9'])
        ->and($observed['destinations'])->toBe(['Marikit'])
        ->and($observed['constraints'][0])->toBe(['pax' => 2]);
});

test('a reply without status or cards is plain chat, not an answer', function () {
    expect(CoverageScorer::observe(['reply' => 'Hello!', 'trace' => ['intent' => 'GENERAL_TALK']])['outcome'])->toBe('chat')
        ->and(CoverageScorer::observe(['reply' => 'x', 'faq' => ['answer' => 'y']])['outcome'])->toBe('answer');
});

test('a turn fails on the first broken expectation and names its class', function () {
    $observed = CoverageScorer::observe([
        'reply' => 'I have booked it for you.',
        'retrieval_outcome' => ['status' => 'matched'],
        'retrieved_rooms' => [['id' => 1, 'destination' => 'Lagawan'], ['id' => 2, 'destination' => 'Lagawan']],
    ]);

    expect(CoverageScorer::scoreTurn(['outcome' => 'answer', 'any' => ['room:2']], $observed)['passed'])->toBeTrue();

    $wrongCards = CoverageScorer::scoreTurn(['outcome' => 'answer', 'none' => ['room:1']], $observed);
    expect($wrongCards['failure'])->toBe('retrieval')->and($wrongCards['failed_checks'])->toBe(['none']);

    $wrongOutcome = CoverageScorer::scoreTurn(['outcome' => 'clarify', 'not_text' => ['booked']], $observed);
    expect($wrongOutcome['failure'])->toBe('dialogue')->and($wrongOutcome['failed_checks'])->toBe(['outcome', 'not_text:booked']);

    expect(CoverageScorer::scoreTurn(['destination' => 'Marikit'], $observed)['failure'])->toBe('retrieval')
        ->and(CoverageScorer::scoreTurn(['facts' => ['ocean']], $observed)['failure'])->toBe('generation');
});

test('missing sources only turn their own checks into data gaps', function () {
    $observed = CoverageScorer::observe([
        'reply' => 'No source.',
        'sections' => [['type' => 'HOTEL_SEARCH', 'outcome' => ['status' => 'matched'], 'constraints' => []]],
        'retrieved_hotels' => [['id' => 3]],
    ]);

    $knowledgeOnly = CoverageScorer::scoreTurn(['tasks' => ['info' => 'answer'], 'facts' => ['Jetty']], $observed, sourceAvailable: false);
    expect($knowledgeOnly['failure'])->toBe('data_gap');

    $catalogMiss = CoverageScorer::scoreTurn(['tasks' => ['info' => 'answer'], 'any' => ['hotel:8']], $observed, sourceAvailable: false);
    expect($catalogMiss['failure'])->toBe('retrieval');
});

test('ordinal references must resolve to the card shown at that position earlier', function () {
    $prior = [1 => ['hotel' => [11, 12, 13]]];
    $second = CoverageScorer::observe(['reply' => '', 'retrieved_hotels' => [['id' => 12]], 'retrieval_outcome' => ['status' => 'matched']]);
    $first = CoverageScorer::observe(['reply' => '', 'retrieved_hotels' => [['id' => 11]], 'retrieval_outcome' => ['status' => 'matched']]);
    $clarified = CoverageScorer::observe(['reply' => 'Which one?', 'retrieval_outcome' => ['status' => 'needs_clarification']]);
    $ref = [['turn' => 1, 'type' => 'hotel', 'position' => 2]];

    expect(CoverageScorer::scoreTurn(['refs' => $ref], $second, $prior)['passed'])->toBeTrue()
        ->and(CoverageScorer::scoreTurn(['refs' => $ref], $first, $prior)['passed'])->toBeFalse()
        ->and(CoverageScorer::scoreTurn(['refs_or_clarify' => $ref], $clarified, $prior)['passed'])->toBeTrue();
});

test('universal forbidden claims catch completed actions but not offers', function () {
    expect(CoverageScorer::forbiddenClaims('Your booking has been confirmed.'))->not->toBe([])
        ->and(CoverageScorer::forbiddenClaims("I've cancelled it."))->not->toBe([])
        ->and(CoverageScorer::forbiddenClaims('I cannot book this for you, but you can add it to your Trip Basket.'))->toBe([]);
});

test('summary separates substantive answers from abstentions and evaluates gates', function () {
    $turn = fn (bool $passed, bool $constraint = false, array $taskChecks = []): array => [
        'checked' => true, 'passed' => $passed, 'failure' => $passed ? null : 'retrieval', 'constraint' => $constraint,
        'task_checks' => $taskChecks, 'forbidden_claims' => [], 'latency_ms' => 10.0,
    ];
    $summary = CoverageScorer::summarize([
        ['id' => 'a', 'category' => 'catalog', 'split' => 'heldout', 'passed' => true, 'answerable' => true, 'turns' => [$turn(true, true, [true])]],
        ['id' => 'b', 'category' => 'catalog', 'split' => 'heldout', 'passed' => false, 'answerable' => true, 'turns' => [$turn(false, true, [false])]],
        ['id' => 'c', 'category' => 'reliability', 'split' => 'heldout', 'passed' => true, 'answerable' => false, 'turns' => [$turn(true)]],
    ]);

    expect($summary['splits']['heldout']['substantive_answer_rate'])->toBe(0.5)
        ->and($summary['splits']['heldout']['correct_abstention_rate'])->toBe(1.0)
        ->and($summary['constraint_correction_accuracy'])->toBe(0.5)
        ->and($summary['task_accounting_rate'])->toBe(0.5)
        ->and($summary['failure_classes']['retrieval'])->toBe(1)
        ->and($summary['gates']['heldout_substantive_answers_90'])->toBeFalse()
        ->and($summary['gates']['security_pricing_ownership_unsupported_actions'])->toBeTrue()
        ->and($summary['gates']['zero_unsupported_claims'])->toBeTrue();
});
