<?php

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');
    config()->set('services.gemini.chat_model', 'models/gemini-2.5-flash-lite');
    config()->set('services.groq.key', 'test-groq-key');
    config()->set('services.groq.chat_model', 'openai/gpt-oss-120b');
    config()->set('services.groq.base_url', 'https://api.groq.com/openai/v1');
});

function groqReply(string $text = 'Groq fallback reply'): array
{
    return [
        'choices' => [
            ['message' => ['role' => 'assistant', 'content' => $text]],
        ],
    ];
}

test('groq fallback answers when gemini returns 500', function () {
    Http::fake([
        '*generativelanguage*' => Http::response(['error' => 'boom'], 500),
        '*api.groq.com*' => Http::response(groqReply()),
    ]);

    $reply = app(GeminiService::class)->generateChatResponse('system', [], 'hello');

    expect($reply)->toBe('Groq fallback reply');
});

test('groq fallback answers when gemini key is missing', function () {
    config()->set('services.gemini.api_key', null);

    Http::fake([
        '*api.groq.com*' => Http::response(groqReply()),
    ]);

    $reply = app(GeminiService::class)->generateChatResponse('system', [], 'hello');

    expect($reply)->toBe('Groq fallback reply');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage'));
});

test('groq is never called when gemini succeeds', function () {
    Http::fake([
        '*generativelanguage*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Gemini reply']]]],
            ],
        ]),
        '*api.groq.com*' => Http::response(groqReply()),
    ]);

    $reply = app(GeminiService::class)->generateChatResponse('system', [], 'hello');

    expect($reply)->toBe('Gemini reply');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.groq.com'));
});

test('null surfaces when both gemini and groq fail', function () {
    Http::fake([
        '*generativelanguage*' => Http::response(['error' => 'boom'], 500),
        '*api.groq.com*' => Http::response(['error' => 'rate limited'], 429),
    ]);

    $reply = app(GeminiService::class)->generateChatResponse('system', [], 'hello');

    expect($reply)->toBeNull();
});

test('gemini model history role maps to assistant in groq payload', function () {
    Http::fake([
        '*generativelanguage*' => Http::response(['error' => 'boom'], 500),
        '*api.groq.com*' => Http::response(groqReply()),
    ]);

    $history = [
        ['role' => 'user', 'parts' => [['text' => 'Hi']]],
        ['role' => 'model', 'parts' => [['text' => 'Hello there']]],
    ];

    app(GeminiService::class)->generateChatResponse('system', $history, 'follow-up');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'api.groq.com')) {
            return false;
        }

        $messages = $request->data()['messages'] ?? [];
        $roles = array_column($messages, 'role');

        return $roles === ['system', 'user', 'assistant', 'user']
            && $request->data()['model'] === 'openai/gpt-oss-120b';
    });
});
