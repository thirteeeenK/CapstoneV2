<?php

use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => [
                'values' => unitVector(0),
            ],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Here is a recommendation for you!'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);
});

test('operational hotel query is not intercepted by similar faq', function () {
    Faq::factory()->create([
        'question' => 'cheap hotels',
        'answer' => 'FAQ answer should not surface',
        'keywords' => 'cheap hotels show',
        'is_active' => true,
    ]);
    $destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    HotelModel::factory()->create([
        'hotel_name' => 'Gate Test Hotel',
        'destination_id' => $destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);

    $response = $this->postJson('/chat', ['message' => 'show me hotels']);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('retrieved_hotels') ?? [])->not->toBeEmpty()
        ->and($response->json('reply'))->not->toContain('FAQ answer should not surface');
});

test('genuine paraphrase matches semantic faq without token overlap', function () {
    Faq::factory()->create([
        'question' => 'What payment methods do you accept?',
        'answer' => 'We accept cash, cards, and e-wallets.',
        'keywords' => 'pay payment',
        'is_active' => true,
        'embedding' => unitVectorString(0),
    ]);

    $response = $this->postJson('/chat', ['message' => 'how can i settle my bill']);

    $response->assertOk()->assertJsonPath('status', 'success')
        ->assertJsonPath('faq.question', 'What payment methods do you accept?');
    expect($response->json('reply'))->toContain('We accept cash, cards, and e-wallets.');
});

test('ambiguous semantic faq match with no clear winner returns null', function () {
    Faq::factory()->create([
        'question' => 'What payment methods do you accept?',
        'answer' => 'We accept cash, cards, and e-wallets.',
        'keywords' => 'pay payment',
        'is_active' => true,
        'embedding' => unitVectorString(0),
    ]);
    Faq::factory()->create([
        'question' => 'What is your refund policy?',
        'answer' => 'Refunds are issued within 7 days.',
        'keywords' => 'refund money back',
        'is_active' => true,
        'embedding' => unitVectorString(0),
    ]);

    $response = $this->postJson('/chat', ['message' => 'how can i settle my bill']);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('faq'))->toBeNull()
        ->and($response->json('reply'))->not->toContain('We accept cash');
});
