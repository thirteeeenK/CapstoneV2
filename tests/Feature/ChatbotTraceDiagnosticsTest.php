<?php

use App\Models\ChatFeedback;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => [
                'values' => unitVector(0),
            ],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Here is some help!'],
                        ],
                    ],
                    'finishReason' => 'STOP',
                    'tokenMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 4],
                ],
            ],
        ]),
    ]);
});

test('support trace carries generation diagnostics', function () {
    $response = $this->postJson('/chat', ['message' => 'talk to a human']);

    $response->assertOk()->assertJsonPath('status', 'success');
    $trace = $response->json('trace') ?? [];
    expect($trace['intent'] ?? null)->toBe('SUPPORT_AGENT')
        ->and($trace)->toHaveKeys(['intent_initial', 'rejection_ids', 'personalized', 'finish_reason', 'candidate_count', 'token_usage', 'latency_ms', 'prompt_hash'])
        ->and($trace['intent_initial'] ?? null)->toBe('SUPPORT_AGENT')
        ->and($trace['rejection_ids'] ?? null)->toBe([]);
});

test('feedback persists against a trace id', function () {
    $chat = $this->postJson('/chat', ['message' => 'stop']);
    $chat->assertOk();
    $traceId = $chat->json('trace.trace_id');
    expect($traceId)->not->toBeEmpty();

    $response = $this->postJson('/chat/feedback', [
        'trace_id' => $traceId,
        'helpful' => false,
        'expected_answer' => 'A human agent, please.',
    ]);

    $response->assertCreated();
    expect(ChatFeedback::where('trace_id', $traceId)->count())->toBe(1);
});

test('feedback without a trace id is rejected', function () {
    $response = $this->postJson('/chat/feedback', ['helpful' => true]);

    $response->assertStatus(422);
});
