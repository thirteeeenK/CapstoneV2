<?php

namespace App\Services\Chat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI-compatible chat fallback used only when Gemini cannot produce a
 * reply (missing/broken key, timeout, rate limit, 5xx). Never throws: every
 * failure path returns null so the caller falls through to the deterministic
 * apology/cards. Chat replies only — embeddings stay Gemini-side.
 */
class GroqChatService
{
    /**
     * @param  array<int, array{role: string, parts: array<int, array{text: string}>}>  $history  Gemini-shaped turns.
     */
    public function chat(string $systemInstruction, array $history, string $userPrompt, bool $conversational = false): ?string
    {
        $startedAt = (int) (microtime(true) * 1000);

        $apiKey = config('services.groq.key');
        if (! $apiKey) {
            Log::warning('Groq API key is not configured; skipping fallback.');

            return null;
        }

        $model = config('services.groq.chat_model') ?? 'openai/gpt-oss-120b';
        $baseUrl = rtrim(config('services.groq.base_url') ?? 'https://api.groq.com/openai/v1', '/');
        $url = $baseUrl.'/chat/completions';

        $messages = [['role' => 'system', 'content' => $systemInstruction]];
        foreach ($history as $entry) {
            $role = $entry['role'] ?? 'user';
            $text = $entry['parts'][0]['text'] ?? '';
            if (! is_string($text) || trim($text) === '') {
                continue;
            }
            $messages[] = [
                'role' => $role === 'model' ? 'assistant' : $role,
                'content' => $text,
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        // Mirror the Gemini grounded/conversational split so the fallback
        // answers with the same determinism contract. Groq coerces 0 to
        // 1e-8 server-side, so passing 0 through is safe.
        $temperature = $conversational
            ? (float) config('services.gemini.chat_conversational_temperature', 0.2)
            : (float) config('services.gemini.chat_grounded_temperature', 0);

        try {
            $response = Http::withToken($apiKey)->timeout(20)->post($url, [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 1024,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Groq fallback timeout: '.$e->getMessage());

            return null;
        } catch (\Exception $e) {
            Log::warning('Groq fallback exception: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Groq fallback failed.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        // gpt-oss also returns a separate reasoning_content field; only the
        // message content carries the user-facing grounded reply.
        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            Log::warning('Groq fallback returned empty content.', ['status' => $response->status()]);

            return null;
        }

        Log::info('Groq fallback succeeded.', [
            'model' => $model,
            'latency_ms' => (int) (microtime(true) * 1000) - $startedAt,
        ]);

        return trim($text);
    }
}
