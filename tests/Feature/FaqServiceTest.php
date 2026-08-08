<?php

use App\Models\Faq;
use App\Services\Chat\FaqService;
use App\Services\GeminiService;

beforeEach(function () {
    $embedding = '[' . implode(',', array_fill(0, 3072, '0.01')) . ']';
    Faq::create([
        'question' => 'Does SunnyTrips support airline ticket booking?',
        'answer' => 'No, SunnyTrips does not support airline ticket booking.',
        'keywords' => 'airline, flight, plane ticket, book flights',
        'category' => 'Services',
        'sort_order' => 0,
        'is_active' => true,
    ])->forceFill(['embedding' => $embedding])->save();
});

test('short conversational queries never match an FAQ even when semantically close', function () {
    $gemini = $this->mock(GeminiService::class);
    $gemini->shouldReceive('searchFaqs')->andReturn([[
        'item' => Faq::first(),
        'score' => 0.95,
    ]]);

    $service = new FaqService($gemini);

    expect($service->findBestMatch('why?'))->toBeNull();
    expect($service->findBestMatch('how much are they?'))->toBeNull();
});

test('semantic FAQ match requires a shared token', function () {
    $gemini = $this->mock(GeminiService::class);
    $gemini->shouldReceive('searchFaqs')->andReturn([[
        'item' => Faq::first(),
        'score' => 0.9,
    ]]);

    $service = new FaqService($gemini);

    expect($service->findBestMatch('do you book plane tickets?'))->not->toBeNull();

    expect($service->findBestMatch('refund my money now'))->toBeNull();
});
