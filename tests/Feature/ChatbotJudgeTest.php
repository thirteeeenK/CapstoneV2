<?php

use App\Services\ChatbotJudgeService;
use App\Services\GeminiService;

/**
 * Stubs the judge model. $expectedPrompt is the fully substituted prompt the
 * service is expected to send; $responses are the raw judge replies, in order.
 */
function judgeMock(string $expectedPrompt, mixed ...$responses): GeminiService
{
    $gemini = Mockery::mock(GeminiService::class);
    $gemini->shouldReceive('loadSystemPrompt')
        ->once()
        ->with('chatbot-judge-prompt.md')
        ->andReturn("Q:{QUERY}\nC:{CONTEXT}\nA:{REPLY}\nR:{REFERENCE}");
    $gemini->shouldReceive('generateContentWithModel')
        ->times(count($responses))
        ->withArgs(function (string $model, string $instruction, string $built, float $temperature) use ($expectedPrompt) {
            expect($instruction)->toBe('You are a strict JSON evaluation grader.');
            expect($temperature)->toBe(0.0);
            expect($built)->toBe($expectedPrompt);

            return $model !== '';
        })
        ->andReturn(...$responses);
    app()->instance(GeminiService::class, $gemini);

    return $gemini;
}

test('it scores faithfulness relevancy and correctness on a one to four scale', function () {
    judgeMock(
        "Q:Which rooms are under 4000?\nC:- [Room] Eval Standard Room | base_price: 3000\nA:Here are our rooms.\nR:The answer should surface at least one of these results: Eval Standard Room.",
        '{"faithfulness":4,"answer_relevancy":3,"answer_correctness":2,"reasoning":"Price claim is unsupported."}'
    );

    $result = app(ChatbotJudgeService::class)->scoreCase(
        'room-price',
        'Which rooms are under 4000?',
        'Here are our rooms.',
        '- [Room] Eval Standard Room | base_price: 3000',
        'The answer should surface at least one of these results: Eval Standard Room.'
    );

    expect($result)
        ->toMatchArray([
            'id' => 'room-price',
            'faithfulness' => 4,
            'answer_relevancy' => 3,
            'answer_correctness' => 2,
        ])
        ->and($result['reasoning'])->toBe('Price claim is unsupported.')
        ->and($result['model'])->not->toBeEmpty();
});

test('it rejects a five point score and retries once', function () {
    judgeMock(
        "Q:q\nC:ctx\nA:a\nR:ref",
        '{"faithfulness":5,"answer_relevancy":4,"answer_correctness":4,"reasoning":"Old scale."}',
        '{"faithfulness":4,"answer_relevancy":4,"answer_correctness":4,"reasoning":"Fully grounded."}'
    );

    $result = app(ChatbotJudgeService::class)->scoreCase('c', 'q', 'a', 'ctx', 'ref');

    expect($result['faithfulness'])->toBe(4)
        ->and($result['answer_correctness'])->toBe(4);
});

test('it accepts a strict JSON response wrapped in a markdown fence', function () {
    judgeMock(
        "Q:q\nC:ctx\nA:a\nR:ref",
        "```json\n{\"faithfulness\":4,\"answer_relevancy\":4,\"answer_correctness\":4,\"reasoning\":\"All claims are supported.\"}\n```"
    );

    $result = app(ChatbotJudgeService::class)->scoreCase('c', 'q', 'a', 'ctx', 'ref');

    expect($result['faithfulness'])->toBe(4)
        ->and($result['answer_correctness'])->toBe(4);
});

test('it accepts valid judge JSON surrounded by explanatory prose', function () {
    judgeMock(
        "Q:q\nC:ctx\nA:a\nR:ref",
        "Evaluation complete.\n{\"faithfulness\":4,\"answer_relevancy\":3,\"answer_correctness\":4,\"reasoning\":\"The response is supported by the retrieved record.\"}\nEnd of evaluation."
    );

    $result = app(ChatbotJudgeService::class)->scoreCase('c', 'q', 'a', 'ctx', 'ref');

    expect($result['faithfulness'])->toBe(4)
        ->and($result['answer_relevancy'])->toBe(3)
        ->and($result['answer_correctness'])->toBe(4);
});

test('it gives up after two invalid judge responses', function () {
    judgeMock(
        "Q:q\nC:ctx\nA:a\nR:ref",
        'not json',
        '{"faithfulness":0,"answer_relevancy":1,"answer_correctness":1,"reasoning":"Out of range."}'
    );

    $result = app(ChatbotJudgeService::class)->scoreCase('c', 'q', 'a', 'ctx', 'ref');

    expect($result)->toBe(['id' => 'c', 'error' => 'judge returned invalid JSON twice']);
});

test('it skips correctness when no reference answer exists', function () {
    judgeMock(
        "Q:Any hotels in Cebu?\nC:(no records)\nA:We have none.\nR:(none supplied — omit answer_correctness)",
        '{"faithfulness":4,"answer_relevancy":4,"reasoning":"Honest abstention, no invented result."}'
    );

    $result = app(ChatbotJudgeService::class)->scoreCase('cebu-hotel-empty', 'Any hotels in Cebu?', 'We have none.', '(no records)', null);

    expect($result['faithfulness'])->toBe(4)
        ->and($result['answer_relevancy'])->toBe(4)
        ->and($result['answer_correctness'])->toBeNull();
});

test('it rejects a correctness score when no reference was supplied', function () {
    judgeMock(
        "Q:q\nC:ctx\nA:a\nR:(none supplied — omit answer_correctness)",
        '{"faithfulness":4,"answer_relevancy":4,"answer_correctness":3,"reasoning":"Invented one."}',
        '{"faithfulness":1,"answer_relevancy":4,"answer_correctness":3,"reasoning":"Invented one."}'
    );

    $result = app(ChatbotJudgeService::class)->scoreCase('c', 'q', 'a', 'ctx', null);

    expect($result['error'])->toBe('judge returned invalid JSON twice');
});

test('it refuses to score an empty chatbot answer', function () {
    $result = app(ChatbotJudgeService::class)->scoreCase('c', 'q', '   ', 'ctx', 'ref');

    expect($result)->toBe(['id' => 'c', 'error' => 'empty chatbot answer']);
});

test('macro means average each metric only over the cases that were scored for it', function () {
    $macro = app(ChatbotJudgeService::class)->macroMeans([
        ['faithfulness' => 4, 'answer_relevancy' => 2, 'answer_correctness' => 4],
        ['faithfulness' => 2, 'answer_relevancy' => 4, 'answer_correctness' => null],
    ]);

    expect($macro)->toBe([
        'n' => 2,
        'faithfulness' => 3.0,
        'answer_relevancy' => 3.0,
        'answer_correctness' => 4.0,
        'correctness_n' => 1,
    ]);
});
