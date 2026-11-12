<?php

use App\Services\Chat\ChatbotService;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;

test('a cut-off reply loses only its unfinished tail', function (string $text, string $expected) {
    expect(GeminiService::withoutUnfinishedTail($text))->toBe($expected);
})->with([
    'complete text is untouched' => ['Palm Resort is quiet. It suits families.', 'Palm Resort is quiet. It suits families.'],
    'trailing fragment removed' => ['Palm Resort is quiet. It also has a large', 'Palm Resort is quiet.'],
    'half-written list item removed' => ["Day 1: Arrive and rest.\n- Island hopping in the morning.\n- Sunset at Wh", "Day 1: Arrive and rest.\n- Island hopping in the morning."],
    'nothing complete' => ['Palm Resort is a quiet place with', ''],
]);

test('a reply stopped by the token limit is returned without its unfinished sentence', function () {
    config(['services.gemini.api_key' => 'test-key', 'services.gemini.chat_context_cache' => false]);
    Http::fake(['*generateContent*' => Http::response(['candidates' => [[
        'content' => ['parts' => [['text' => 'Palm Resort is quiet. It also has a large']]], 'finishReason' => 'MAX_TOKENS',
    ]]])]);

    expect(app(GeminiService::class)->generateChatResponse('system', [], 'question'))->toBe('Palm Resort is quiet.');
});

test('narration keeps its layout and drops only sentences with an unknown amount or a claimed action', function () {
    $guard = (new ReflectionClass(ChatbotService::class))->getMethod('guardNarration');
    $service = app(ChatbotService::class);
    $prompt = 'Room rate ₱2,500.00 per night. Total ₱7,500.00.';
    $reply = "### Day 1\n- Check in. Your room is ₱2,500.00 per night.\n- Dinner costs about ₱900 per person.\n\n### Day 2\nI have booked the island tour for you. The total is ₱7,500.00.";

    expect($guard->invoke($service, $reply, $prompt))
        ->toBe("### Day 1\n- Check in. Your room is ₱2,500.00 per night.\n\n### Day 2\nThe total is ₱7,500.00.")
        ->and($guard->invoke($service, 'Your booking has been confirmed and paid.', $prompt, false))->toBe('Your booking has been confirmed and paid.');
});
