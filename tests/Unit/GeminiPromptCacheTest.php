<?php

use App\Services\GeminiService;
use App\Services\RoomAvailabilityService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

uses(TestCase::class);

test('loadSystemPrompt returns file content and caches it', function () {
    Cache::flush();

    $availability = Mockery::mock(RoomAvailabilityService::class);
    $availability->shouldReceive('check')->andReturn(['available' => true]);

    $service = new class($availability) extends GeminiService
    {
        public function loadPublic(string $f): string
        {
            return $this->loadSystemPrompt($f);
        }
    };

    expect($service->loadPublic('chatbot-system-prompt.md'))->toContain('SunnyBot');
    expect(Cache::has('gemini:system_prompt:chatbot-system-prompt.md'))->toBeTrue();
});

test('loadSystemPrompt falls back to disk when cache read fails', function () {
    Cache::shouldReceive('get')
        ->once()
        ->with('gemini:system_prompt:sentiment-analysis-prompt.md')
        ->andThrow(new RuntimeException('cache down'));

    $availability = Mockery::mock(RoomAvailabilityService::class);
    $availability->shouldReceive('check')->andReturn(['available' => true]);

    $service = new class($availability) extends GeminiService
    {
        public function loadPublic(string $f): string
        {
            return $this->loadSystemPrompt($f);
        }
    };

    expect($service->loadPublic('sentiment-analysis-prompt.md'))->toContain('SunnyTrips');
});

test('loadSystemPrompt returns content even when cache write fails', function () {
    Cache::shouldReceive('get')
        ->once()
        ->with('gemini:system_prompt:review-summary-prompt.md')
        ->andReturn(null);
    Cache::shouldReceive('put')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    $availability = Mockery::mock(RoomAvailabilityService::class);
    $availability->shouldReceive('check')->andReturn(['available' => true]);

    $service = new class($availability) extends GeminiService
    {
        public function loadPublic(string $f): string
        {
            return $this->loadSystemPrompt($f);
        }
    };

    expect($service->loadPublic('review-summary-prompt.md'))->toContain('consensus');
});
