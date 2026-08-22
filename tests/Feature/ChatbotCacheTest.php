<?php

use App\Services\GeminiService;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('chat uses cachedContent and omits systemInstruction when caching enabled', function () {
    config(['services.gemini.chat_context_cache' => true]);
    Cache::flush();

    $cacheCreatedPayload = null;
    $generatePayload = null;

    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*cachedContents*' => function (Request $request) use (&$cacheCreatedPayload) {
            $cacheCreatedPayload = $request->data();

            return Http::response([
                'name' => 'cachedContents/test123',
                'expireTime' => now()->addDay()->toIso8601String(),
            ]);
        },
        '*generateContent*' => function (Request $request) use (&$generatePayload) {
            $generatePayload = $request->data();

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Hello!']]]]]]);
        },
    ]);

    $user = onboardedUser();
    $this->actingAs($user);
    $this->postJson('/chat', ['message' => 'Hello there'])->assertOk();

    expect($cacheCreatedPayload['systemInstruction']['parts'][0]['text'])->toContain('SunnyBot');
    expect($generatePayload)->toHaveKey('cachedContent', 'cachedContents/test123');
    expect($generatePayload)->not->toHaveKey('systemInstruction');
});

test('chat falls back to inline systemInstruction when cache create fails', function () {
    config(['services.gemini.chat_context_cache' => true]);
    Cache::flush();

    $generatePayload = null;
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*cachedContents*' => Http::response([], 500),
        '*generateContent*' => function (Request $request) use (&$generatePayload) {
            $generatePayload = $request->data();

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Hello!']]]]]]);
        },
    ]);

    $user = onboardedUser();
    $this->actingAs($user);
    $this->postJson('/chat', ['message' => 'Hello there'])->assertOk();

    expect($generatePayload)->toHaveKey('systemInstruction');
    expect($generatePayload)->not->toHaveKey('cachedContent');
});

test('chat falls back to inline systemInstruction when cachedContent is rejected at generate time', function () {
    config(['services.gemini.chat_context_cache' => true]);
    Cache::flush();

    $generatePayloads = [];
    $generateCallCount = 0;

    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*cachedContents*' => function (Request $request) {
            return Http::response([
                'name' => 'cachedContents/test123',
                'expireTime' => now()->addDay()->toIso8601String(),
            ]);
        },
        '*generateContent*' => function (Request $request) use (&$generatePayloads, &$generateCallCount) {
            $generatePayloads[] = $request->data();
            $generateCallCount++;

            if ($generateCallCount === 1) {
                return Http::response([], 404);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Hello!']]]]]]);
        },
    ]);

    $user = onboardedUser();
    $this->actingAs($user);
    $this->postJson('/chat', ['message' => 'Hello there'])->assertOk();

    $systemInstruction = app(GeminiService::class)->loadChatbotSystemPrompt();
    $modelName = config('services.gemini.chat_model') ?? 'models/gemini-2.5-flash-lite';
    $cacheKey = 'gemini:chat_cache:'.md5($modelName.'|'.$systemInstruction);

    expect($generatePayloads)->toHaveCount(2);
    expect($generatePayloads[0])->toHaveKey('cachedContent', 'cachedContents/test123');
    expect($generatePayloads[0])->not->toHaveKey('systemInstruction');
    expect($generatePayloads[1])->toHaveKey('systemInstruction');
    expect($generatePayloads[1])->not->toHaveKey('cachedContent');
    expect(Cache::get($cacheKey))->toBeNull();
});

test('chat caches a negative marker when cache create fails and skips subsequent create attempts', function () {
    config(['services.gemini.chat_context_cache' => true]);
    Cache::flush();

    $createCallCount = 0;
    $generatePayloads = [];

    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*cachedContents*' => function (Request $request) use (&$createCallCount) {
            $createCallCount++;

            return Http::response([], 500);
        },
        '*generateContent*' => function (Request $request) use (&$generatePayloads) {
            $generatePayloads[] = $request->data();

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Hello!']]]]]]);
        },
    ]);

    $user = onboardedUser();
    $this->actingAs($user);

    $this->postJson('/chat', ['message' => 'Hello there'])->assertOk();
    $this->postJson('/chat', ['message' => 'How are you?'])->assertOk();

    expect($createCallCount)->toBe(1);
    expect($generatePayloads)->toHaveCount(2);
    expect($generatePayloads[0])->toHaveKey('systemInstruction');
    expect($generatePayloads[1])->toHaveKey('systemInstruction');
});

test('chat falls back to inline systemInstruction when local cache read throws', function () {
    config(['services.gemini.chat_context_cache' => true]);
    Cache::flush();

    $createCallCount = 0;
    $generatePayload = null;

    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*cachedContents*' => function (Request $request) use (&$createCallCount) {
            $createCallCount++;

            return Http::response([
                'name' => 'cachedContents/should-not-be-called',
                'expireTime' => now()->addDay()->toIso8601String(),
            ]);
        },
        '*generateContent*' => function (Request $request) use (&$generatePayload) {
            $generatePayload = $request->data();

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Hello!']]]]]]);
        },
    ]);

    $defaultRepo = app('cache')->store();
    $throwingRepo = new class($defaultRepo->getStore()) extends Repository
    {
        public function get($key, $default = null): mixed
        {
            throw new RuntimeException('Simulated cache read failure');
        }
    };
    Cache::swap($throwingRepo);

    try {
        $user = onboardedUser();
        $this->actingAs($user);
        $this->postJson('/chat', ['message' => 'Hello there'])->assertOk();
    } finally {
        Cache::swap($defaultRepo);
    }

    expect($createCallCount)->toBe(0);
    expect($generatePayload)->toHaveKey('systemInstruction');
    expect($generatePayload)->not->toHaveKey('cachedContent');
});

test('chat does not retry generateContent when first attempt returns a non-cache-related 500', function () {
    config(['services.gemini.chat_context_cache' => true]);
    Cache::flush();

    $generateCallCount = 0;
    $generatePayloads = [];

    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*cachedContents*' => function (Request $request) {
            return Http::response([
                'name' => 'cachedContents/test123',
                'expireTime' => now()->addDay()->toIso8601String(),
            ]);
        },
        '*generateContent*' => function (Request $request) use (&$generateCallCount, &$generatePayloads) {
            $generateCallCount++;
            $generatePayloads[] = $request->data();

            return Http::response([], 500);
        },
    ]);

    $user = onboardedUser();
    $this->actingAs($user);
    $this->postJson('/chat', ['message' => 'Hello there'])->assertOk();

    expect($generateCallCount)->toBe(1);
    expect($generatePayloads)->toHaveCount(1);
    expect($generatePayloads[0])->toHaveKey('cachedContent', 'cachedContents/test123');
});
