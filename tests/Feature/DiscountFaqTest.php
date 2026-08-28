<?php

use Database\Seeders\FaqSeeder;
use Illuminate\Support\Facades\Http;

test('discount FAQ answers does sunnytrips offer discounts', function () {
    $this->seed(FaqSeeder::class);
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Should not be called']]]]]]),
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
    ]);

    $response = $this->postJson('/chat', ['message' => 'does sunnytrips offer discounts?']);
    $response->assertOk()->assertJsonPath('faq.question', 'Does SunnyTrips offer discounts?');
    expect($response->json('reply'))->toContain('Passenger Pricing Rules')->toContain('₱50');
});

test('senior discount query hits discount FAQ', function () {
    $this->seed(FaqSeeder::class);
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Should not be called']]]]]]),
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
    ]);

    $response = $this->postJson('/chat', ['message' => 'do you have senior discount?']);
    $response->assertOk()->assertJsonPath('faq.question', 'Does SunnyTrips offer discounts?');
});

test('foreigner surcharge query hits discount FAQ', function () {
    $this->seed(FaqSeeder::class);
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Should not be called']]]]]]),
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
    ]);

    $response = $this->postJson('/chat', ['message' => 'foreigner surcharge?']);
    $response->assertOk()->assertJsonPath('faq.question', 'Does SunnyTrips offer discounts?');
});

test('bare discount single token hits FAQ via allowlist', function () {
    $this->seed(FaqSeeder::class);
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Should not be called']]]]]]),
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
    ]);
    $response = $this->postJson('/chat', ['message' => 'discount']);
    $response->assertOk()->assertJsonPath('faq.question', 'Does SunnyTrips offer discounts?');
    expect($response->json('reply'))->toContain('₱50');
});

test('taglish meron bang discount dito hits discount via intent', function () {
    $this->seed(FaqSeeder::class);
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'LLM fallback should not be called for discount intent']]]]]]),
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
    ]);
    $response = $this->postJson('/chat', ['message' => 'meron bang discount dito?']);
    $response->assertOk();
    // Should be either FAQ or DISCOUNT_QUERY handler, both contain bullets
    expect($response->json('reply'))->toContain('₱50')->toContain('Student');
});

test('how much discount is offered in sunnytrips hits discount', function () {
    $this->seed(FaqSeeder::class);
    Http::fake([
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'LLM fallback should not be called']]]]]]),
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
    ]);
    $response = $this->postJson('/chat', ['message' => 'how much discount is offered in sunnytrips']);
    $response->assertOk();
    expect($response->json('reply'))->toContain('₱50')->toContain('Passenger Pricing Rules');
});
