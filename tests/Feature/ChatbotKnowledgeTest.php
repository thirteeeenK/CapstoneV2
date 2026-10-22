<?php

use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\KnowledgeArticle;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.chatbot.planner_enabled' => false, 'services.chatbot.conversational_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]]),
    ]);
    $this->destination = DestinationModel::factory()->create(['name' => 'Lagawan']);
    $this->hotel = HotelModel::factory()->create(['hotel_name' => 'Bangin Cliff Lodge', 'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);
    $this->article = KnowledgeArticle::factory()->create([
        'destination_id' => $this->destination->id, 'category' => 'dining', 'title' => 'Dining in Lagawan',
        'body' => 'The Bangketa Food Strip in town has seafood restaurants and cafes.', 'source_label' => 'Lagawan tourism office',
        'source_url' => 'https://tourism.example.test/dining',
    ]);
});

test('a mixed request returns catalog cards and cited guidance in separate sections', function () {
    $response = $this->postJson('/chat', ['message' => 'hotels in Lagawan and where to eat there']);

    $response->assertOk()
        ->assertJsonPath('sections.0.type', 'HOTEL_SEARCH')
        ->assertJsonPath('sections.0.data.retrieved_hotels.0.id', $this->hotel->id)
        ->assertJsonPath('sections.1.type', 'KNOWLEDGE_QUERY')
        ->assertJsonPath('sections.1.outcome.status', 'matched')
        ->assertJsonPath('knowledge.0.id', $this->article->id)
        ->assertJsonPath('knowledge.0.content_hash', $this->article->contentHash());
    expect($response->json('sections.1.answer'))->toContain('Bangketa Food Strip')->toContain('Lagawan tourism office')
        ->and($response->json('sections.1.data.retrieved_hotels'))->toBeNull()
        ->and($response->json('trace.tasks.1.knowledge_references.0.id'))->toBe($this->article->id);
});

test('without reviewed guidance the bot abstains and keeps the hotel results', function () {
    $this->article->update(['status' => 'draft']);

    $response = $this->postJson('/chat', ['message' => 'hotels in Lagawan and where to eat there']);

    $response->assertOk()
        ->assertJsonPath('sections.0.data.retrieved_hotels.0.id', $this->hotel->id)
        ->assertJsonPath('sections.1.outcome.status', 'no_match')
        ->assertJsonPath('sections.1.recovery', 'absent_data');
    expect($response->json('sections.1.answer'))->not->toContain('Bangketa');
});

test('timetables stay unsupported because no source exists for them', function () {
    $this->postJson('/chat', ['message' => 'hotels in Lagawan and a bus schedule from the airport'])
        ->assertOk()
        ->assertJsonPath('sections.1.type', 'UNSUPPORTED_INFORMATION')
        ->assertJsonPath('sections.1.outcome.reason', 'data_source_not_supported');
});

test('guidance for one destination never answers another destination', function () {
    $marikit = DestinationModel::factory()->create(['name' => 'Marikit']);
    HotelModel::factory()->create(['destination_id' => $marikit->id, 'is_shown' => true, 'embedding' => unitVectorString(0)]);

    $response = $this->postJson('/chat', ['message' => 'where can we eat in Marikit?']);

    $response->assertOk()->assertJsonPath('sections.0.outcome.status', 'no_match');
    expect($response->json('reply'))->not->toContain('Bangketa');
});

test('restored history marks a citation whose article was withdrawn', function () {
    $first = $this->postJson('/chat', ['message' => 'where can we eat in Lagawan?']);
    $first->assertOk()->assertJsonPath('knowledge.0.id', $this->article->id);
    $this->article->update(['status' => 'archived']);

    $history = $this->getJson('/chat/history?session_token='.$first->json('session_token'));

    $bot = collect($history->json('messages'))->firstWhere('sender', 'bot');
    expect($bot['context_data']['knowledge'][0]['current_status'])->toBe('withdrawn')
        ->and($bot['context_data']['sections'][0]['data']['knowledge'][0]['current_status'])->toBe('withdrawn');
});

test('policy questions are answered from FAQs with their review state before task planning', function () {
    Faq::create(['question' => 'Can I cancel my booking and get a refund?', 'answer' => 'Cancellations 7 days before check-in are refunded.', 'keywords' => 'cancel, refund', 'is_active' => true]);

    $response = $this->postJson('/chat', ['message' => 'can I cancel my booking and get a refund?']);

    $response->assertOk()->assertJsonPath('faq.reviewed', false)->assertJsonMissingPath('sections');
    expect($response->json('reply'))->toContain('7 days')
        ->and($response->json('evidence_note'))->toContain('review date not recorded');
});

test('a capability question gets the fixed capability overview without a model call', function () {
    $response = $this->postJson('/chat', ['message' => 'what can you do?']);

    $response->assertOk()->assertJsonPath('retrieval_outcome.reason', 'capability_overview');
    expect($response->json('reply'))->toContain('cannot')->toContain('hotels');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generateContent'));
});
