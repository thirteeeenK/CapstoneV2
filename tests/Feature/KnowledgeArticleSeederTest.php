<?php

use App\Models\DestinationModel;
use App\Models\KnowledgeArticle;
use Database\Seeders\KnowledgeArticleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.chatbot.guided_conversation_enabled' => true, 'services.chatbot.planner_enabled' => false, 'services.chatbot.turn_interpreter' => false]);
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]]),
    ]);
    $this->boracay = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->elNido = DestinationModel::factory()->create(['name' => 'El Nido']);
});

test('the seeder publishes citable guidance for every category and can be run again without duplicates', function () {
    $this->seed(KnowledgeArticleSeeder::class);
    $count = KnowledgeArticle::count();
    $this->seed(KnowledgeArticleSeeder::class);

    expect(KnowledgeArticle::count())->toBe($count)->toBe(18)
        ->and(KnowledgeArticle::citable()->count())->toBe(18);
    foreach ([$this->boracay, $this->elNido] as $destination) {
        expect(KnowledgeArticle::where('destination_id', $destination->id)->distinct()->pluck('category')->sort()->values()->all())
            ->toBe(collect(array_keys(KnowledgeArticle::CATEGORIES))->sort()->values()->all());
    }
});

test('every seeded article fits the quoted excerpt and contains nothing the safety filter withholds', function () {
    $this->seed(KnowledgeArticleSeeder::class);

    foreach (KnowledgeArticle::all() as $article) {
        expect(mb_strlen($article->body))->toBeLessThanOrEqual(600, $article->title)
            ->and($article->hasInstructionLikeText())->toBeFalse($article->title);
    }
});

test('the chatbot answers common destination questions from the seeded guidance', function (string $question, string $expected) {
    $this->seed(KnowledgeArticleSeeder::class);

    $response = $this->postJson('/chat', ['message' => $question])->assertOk();

    expect($response->json('reply'))->toContain($expected)
        ->and($response->json('knowledge.0.title'))->not->toBeNull();
})->with([
    'getting there' => ['how do I get to Boracay from Manila?', 'Caticlan Jetty Port'],
    'getting around' => ['how do we get around El Nido?', 'Tricycles are the main transport'],
    'dining' => ['where can we eat in Boracay?', "D'Mall"],
    'best time' => ['when is the best time to visit El Nido?', 'December to May'],
    'packing' => ['what to bring to El Nido?', 'dry bag'],
    'attractions' => ['what are the best beaches in Boracay?', 'Puka Shell Beach'],
    'fees' => ['is there an environmental fee in Boracay?', 'environmental fee'],
    'accessibility' => ['is El Nido wheelchair accessible?', 'limited mobility'],
]);
