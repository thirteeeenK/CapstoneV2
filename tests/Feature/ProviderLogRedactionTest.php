<?php

use App\Services\GeminiService;
use App\Services\ProviderLogRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Sentinel that must never reach a log line, a user-facing response or
 * persisted chat state. Shaped like a real Gemini key so the prefix rule and
 * the configured-secret rule are both exercised.
 */
const SENTINEL_KEY = 'AIzaSyFAKE-sentinel-key-DO-NOT-LOG-0001';

/**
 * Collects every logged message plus its serialized context. Returns an
 * object so the caller observes lines appended after this call.
 *
 * @return ArrayObject<int, string>
 */
function captureLogLines(): ArrayObject
{
    /** @var ArrayObject<int, string> $lines */
    $lines = new ArrayObject;

    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($lines) {
        $lines->append($event->message.' '.json_encode($event->context));
    });

    return $lines;
}

beforeEach(function () {
    config()->set('services.gemini.api_key', SENTINEL_KEY);
    config()->set('services.gemini.chat_model', 'models/gemini-2.5-flash-lite');
    config()->set('services.gemini.embedding_model', 'models/text-embedding-001');
    config()->set('services.groq.key', null);
});

test('scrub removes the configured provider secret', function () {
    $scrubbed = ProviderLogRedactor::scrub('cURL error 28 for https://x/v1beta/m:generateContent?key='.SENTINEL_KEY);

    expect($scrubbed)->not->toContain(SENTINEL_KEY)
        ->and($scrubbed)->toContain('[redacted]');
});

test('scrub removes credential shapes that are not the configured secret', function () {
    config()->set('services.gemini.api_key', null);

    $samples = [
        'GET /v1beta/models?key=AIzaSyOTHERKEY1234567890 failed' => 'AIzaSyOTHERKEY1234567890',
        'Authorization: Bearer gsk_abcdef1234567890abcdef' => 'gsk_abcdef1234567890abcdef',
        '{"api_key": "sk_live_abcdef1234567890"}' => 'sk_live_abcdef1234567890',
        'https://api.openweathermap.org/data/2.5/forecast?appid=0123456789abcdef' => '0123456789abcdef',
    ];

    foreach ($samples as $input => $secret) {
        expect(ProviderLogRedactor::scrub($input))->not->toContain($secret);
    }
});

test('scrub bounds the snippet length', function () {
    // mb_strimwidth counts the trailing marker inside the limit.
    expect(ProviderLogRedactor::scrub(str_repeat('a', 5000)))
        ->toHaveLength(ProviderLogRedactor::SNIPPET_LIMIT);
});

test('gemini requests send the key as a header and never in the url', function () {
    Http::fake(['*generativelanguage*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'Gemini reply']]]]],
    ])]);

    app(GeminiService::class)->generateChatResponse('system', [], 'hello');

    Http::assertSent(function ($request) {
        expect($request->url())->not->toContain(SENTINEL_KEY)
            ->and($request->url())->not->toContain('key=');

        return $request->header('x-goog-api-key')[0] === SENTINEL_KEY;
    });
});

test('a failing gemini response never logs the credential', function () {
    $lines = captureLogLines();

    // A provider error body that echoes the offending request URL.
    Http::fake([
        '*generativelanguage*' => Http::response([
            'error' => [
                'code' => 400,
                'message' => 'API key not valid. Request: https://generativelanguage.googleapis.com/v1beta/models/x:generateContent?key='.SENTINEL_KEY,
            ],
        ], 400),
    ]);

    app(GeminiService::class)->generateChatResponse('system', [], 'hello');

    expect($lines->getArrayCopy())->not->toBeEmpty();
    foreach ($lines as $line) {
        expect($line)->not->toContain(SENTINEL_KEY);
    }
});

test('a transport failure never logs the credential and still falls back', function () {
    $lines = captureLogLines();

    // Guzzle puts the full effective URL into connection-exception messages.
    Http::fake(function () {
        throw new ConnectionException(
            'cURL error 28: Operation timed out after 30000 milliseconds '
            .'(see https://generativelanguage.googleapis.com/v1beta/models/x:generateContent?key='.SENTINEL_KEY.')'
        );
    });

    $reply = app(GeminiService::class)->generateChatResponse('system', [], 'hello');

    expect($reply)->toBeNull();
    expect($lines->getArrayCopy())->not->toBeEmpty();
    foreach ($lines as $line) {
        expect($line)->not->toContain(SENTINEL_KEY);
    }
});

test('a failing embedding call never logs the credential', function () {
    $lines = captureLogLines();

    Http::fake(function () {
        throw new ConnectionException('cURL error 7 for https://generativelanguage.googleapis.com/v1beta/models/text-embedding-001:embedContent?key='.SENTINEL_KEY);
    });

    $embedding = app(GeminiService::class)->generateEmbedding('beach resort');

    expect($embedding)->toBeNull();
    expect($lines->getArrayCopy())->not->toBeEmpty();
    foreach ($lines as $line) {
        expect($line)->not->toContain(SENTINEL_KEY);
    }
});

test('a chat failure response body never carries the credential to the user', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28 ?key='.SENTINEL_KEY);
    });

    $response = $this->postJson('/chat', ['message' => 'any hotel in Boracay?']);

    expect($response->getContent())->not->toContain(SENTINEL_KEY);
});
