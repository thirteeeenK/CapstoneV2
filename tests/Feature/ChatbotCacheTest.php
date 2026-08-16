<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;
use App\Models\User;

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
