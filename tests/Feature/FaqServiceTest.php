<?php

use App\Models\Faq;
use App\Services\Chat\FaqService;
use App\Services\Chat\IntentRouter;
use App\Services\GeminiService;

beforeEach(function () {
    $embedding = '['.implode(',', array_fill(0, 3072, '0.01')).']';
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

test('semantic FAQ match needs score and margin, not a shared token', function () {
    $gemini = $this->mock(GeminiService::class);
    $gemini->shouldReceive('searchFaqs')->andReturn([[
        'item' => Faq::first(),
        'score' => 0.9,
    ]]);

    $service = new FaqService($gemini);

    expect($service->findBestMatch('do you book plane tickets?'))->not->toBeNull();

    // No shared tokens: with the token-overlap veto gone, the service
    // trusts the embedding score instead of second-guessing it.
    expect($service->findBestMatch('refund my money now'))->not->toBeNull();
});

test('tied semantic FAQ winners stay silent', function () {
    $gemini = $this->mock(GeminiService::class);
    $faq = Faq::first();
    $gemini->shouldReceive('searchFaqs')->andReturn([
        ['item' => $faq, 'score' => 0.9],
        ['item' => $faq, 'score' => 0.88],
    ]);

    expect((new FaqService($gemini))->findBestMatch('book plane tickets'))->toBeNull();
});

test('chatbot-only FAQs still match for SunnyBot', function () {
    Faq::create([
        'question' => 'What is the secret internal refund hotline?',
        'answer' => 'Call extension 999.',
        'keywords' => 'secret internal refund hotline',
        'category' => 'Policies',
        'sort_order' => 99,
        'is_active' => true,
        'show_on_landing' => false,
    ]);

    $gemini = $this->mock(GeminiService::class);

    expect((new FaqService($gemini))->findBestMatch('what is the secret internal refund hotline'))->not->toBeNull()
        ->and((new FaqService($gemini))->findBestMatch('what is the secret internal refund hotline')->answer)->toBe('Call extension 999.');
});

test('FAQ-page-only entries are unavailable to SunnyBot', function () {
    Faq::create([
        'question' => 'What is the page-only answer?',
        'answer' => 'This should stay on the public page.',
        'keywords' => 'page only answer',
        'category' => 'Policies',
        'sort_order' => 100,
        'is_active' => false,
        'show_on_landing' => true,
    ]);

    $gemini = $this->mock(GeminiService::class);

    expect((new FaqService($gemini))->findBestMatch('what is the page-only answer'))->toBeNull();
});

test('non-general-talk intents skip FAQs without searching', function () {
    // No searchFaqs stub: any embedding call would fail the mock.
    $gemini = $this->mock(GeminiService::class);

    expect((new FaqService($gemini))->findBestMatch('show me hotels', IntentRouter::HOTEL_SEARCH))->toBeNull();
});
