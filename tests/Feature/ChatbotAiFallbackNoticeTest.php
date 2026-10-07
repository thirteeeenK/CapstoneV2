<?php

use App\Services\Chat\ChatbotService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.gemini.api_key', 'test-gemini-key');
    config()->set('services.gemini.chat_model', 'models/gemini-2.5-flash-lite');
    config()->set('services.groq.key', 'test-groq-key');
    config()->set('services.groq.chat_model', 'openai/gpt-oss-120b');
    config()->set('services.groq.base_url', 'https://api.groq.com/openai/v1');
});

function groqSuccess(string $text = 'Groq backup reply'): array
{
    return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]];
}

function geminiSuccess(string $text = 'Gemini reply'): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
}

test('chat names both vendors when gemini fails over to groq', function () {
    Http::fake([
        '*generativelanguage*' => Http::response(['error' => 'boom'], 500),
        '*api.groq.com*' => Http::response(groqSuccess()),
    ]);

    $response = $this->actingAs(onboardedUser())->postJson('/chat', [
        'message' => 'Hello',
    ]);

    $response->assertOk();
    expect($response->json('reply'))->toBe('Groq backup reply')
        ->and($response->json('fallback_model'))->toBe('groq')
        ->and($response->json('ai_notice'))->toContain('Gemini')
        ->and($response->json('ai_notice'))->toContain('Groq');
});

test('chat names both vendors and offers handoff when gemini and groq fail', function () {
    Http::fake([
        '*generativelanguage*' => Http::response(['error' => 'boom'], 500),
        '*api.groq.com*' => Http::response(['error' => 'rate limited'], 429),
    ]);

    $response = $this->actingAs(onboardedUser())->postJson('/chat', [
        'message' => 'Hello',
    ]);

    $response->assertOk();
    expect($response->json('reply'))->toBe(ChatbotService::AI_OUTAGE_REPLY)
        ->and($response->json('reply'))->toContain('Gemini')
        ->and($response->json('reply'))->toContain('Groq')
        ->and($response->json('suggested_actions'))->toContainEqual([
            'id' => 'talk-to-agent', 'label' => 'Talk to a human agent', 'handoff' => true,
        ]);
});

test('chat carries no fallback notice when gemini succeeds', function () {
    Http::fake([
        '*generativelanguage*' => Http::response(geminiSuccess()),
        '*api.groq.com*' => Http::response(groqSuccess()),
    ]);

    $response = $this->actingAs(onboardedUser())->postJson('/chat', [
        'message' => 'Hello',
    ]);

    $response->assertOk();
    expect($response->json('fallback_model'))->toBeNull()
        ->and($response->json('ai_notice'))->toBeNull();
});
