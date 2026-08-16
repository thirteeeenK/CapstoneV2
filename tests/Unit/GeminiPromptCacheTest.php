<?php

use Illuminate\Support\Facades\Cache;
uses(Tests\TestCase::class);

test('loadSystemPrompt returns file content and caches it', function () {
    Cache::flush();

    $service = new class extends \App\Services\GeminiService {
        public function loadPublic(string $f): string
        {
            return $this->loadSystemPrompt($f);
        }
    };

    expect($service->loadPublic('chatbot-system-prompt.md'))->toContain('SunnyBot');
    expect(Cache::has('gemini:system_prompt:chatbot-system-prompt.md'))->toBeTrue();
});
