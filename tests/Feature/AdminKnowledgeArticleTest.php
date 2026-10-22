<?php

use App\Models\AdminModel;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]])]);
    config(['services.gemini.api_key' => 'test-key']);
    $this->admin = AdminModel::create(['name' => 'Guidance Admin', 'email' => 'guidance@admin.test', 'password' => 'password']);
    $this->destination = DestinationModel::factory()->create(['name' => 'Lagawan']);
    $this->payload = [
        'title' => 'Getting around Lagawan',
        'body' => 'Tricycles are the main way to get around town.',
        'category' => 'local_transport',
        'destination_id' => $this->destination->id,
        'source_label' => 'Lagawan tourism office',
        'source_url' => 'https://tourism.example.test/transport',
        'reviewed_by' => 'Travel desk',
        'reviewed_at' => now()->toDateString(),
        'status' => 'published',
    ];
});

test('only admins can manage travel guidance', function () {
    $this->get(route('admin.knowledge.index'))->assertRedirect();
    $this->actingAs(User::factory()->create())->post(route('admin.knowledge.store'), $this->payload)->assertRedirect();

    expect(KnowledgeArticle::count())->toBe(0);
});

test('a published article is stored with its review metadata and a current embedding', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.knowledge.store'), $this->payload)
        ->assertRedirect(route('admin.knowledge.index'))->assertSessionHasNoErrors();

    $article = KnowledgeArticle::sole();
    expect($article->isCitable())->toBeTrue()
        ->and($article->reviewed_by)->toBe('Travel desk')
        ->and($article->embeddingIsStale())->toBeFalse();
});

test('publishing requires a reviewer and review date while drafts do not', function () {
    $unreviewed = [...$this->payload, 'reviewed_by' => null, 'reviewed_at' => null];

    $this->actingAs($this->admin, 'admin')->post(route('admin.knowledge.store'), $unreviewed)
        ->assertSessionHasErrors(['reviewed_at', 'reviewed_by']);
    $this->actingAs($this->admin, 'admin')->post(route('admin.knowledge.store'), [...$unreviewed, 'status' => 'draft'])
        ->assertSessionHasNoErrors();

    expect(KnowledgeArticle::sole()->status)->toBe('draft');
});

test('reference links must be http or https and future review dates are rejected', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.knowledge.store'), [...$this->payload, 'source_url' => 'javascript:alert(1)', 'reviewed_at' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors(['source_url', 'reviewed_at']);
});

test('changing published text requires a fresh review date', function () {
    $article = KnowledgeArticle::factory()->create([...$this->payload, 'reviewed_at' => now()->subWeek()->toDateString()]);

    $this->actingAs($this->admin, 'admin')->put(route('admin.knowledge.update', $article), [...$this->payload, 'body' => 'Changed text.', 'reviewed_at' => now()->subWeek()->toDateString()])
        ->assertSessionHasErrors('reviewed_at');
    $this->actingAs($this->admin, 'admin')->put(route('admin.knowledge.update', $article), [...$this->payload, 'body' => 'Changed text.'])
        ->assertSessionHasNoErrors();

    expect($article->fresh()->body)->toBe('Changed text.');
});

test('published articles can be corrected and reviewed again on the same day', function () {
    $article = KnowledgeArticle::factory()->create($this->payload);

    $this->actingAs($this->admin, 'admin')->put(route('admin.knowledge.update', $article), [...$this->payload, 'body' => 'Corrected transport guidance.'])
        ->assertSessionHasNoErrors();

    expect($article->fresh()->body)->toBe('Corrected transport guidance.');
});

test('a new unreviewed FAQ cannot be activated through either admin path', function (string $path) {
    $payload = ['question' => 'New refund terms?', 'answer' => 'Contact support for refund eligibility.', 'is_active' => 0];
    $this->actingAs($this->admin, 'admin')->post(route('admin.faqs.store'), $payload)->assertSessionHasNoErrors();
    $faq = Faq::sole();

    $response = $path === 'toggle'
        ? $this->postJson(route('admin.faqs.toggle-visibility', $faq->id))
        : $this->putJson(route('admin.faqs.update', $faq->id), [...$payload, 'is_active' => 1]);

    $response->assertUnprocessable()->assertJsonValidationErrors('reviewed_at');
    expect($faq->fresh()->is_active)->toBeFalse();
})->with(['toggle', 'edit']);

test('inactive answer edits invalidate old reviews before visibility can be toggled', function () {
    $faq = Faq::factory()->create(['is_active' => false, 'source_label' => 'Policy owner', 'reviewed_at' => today()->subDay()]);

    $this->actingAs($this->admin, 'admin')->put(route('admin.faqs.update', $faq->id), [
        'question' => $faq->question, 'answer' => 'Changed policy.', 'is_active' => 0,
        'source_label' => $faq->source_label, 'reviewed_at' => $faq->reviewed_at->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->postJson(route('admin.faqs.toggle-visibility', $faq->id))->assertUnprocessable();
    expect($faq->fresh()->isReviewed())->toBeFalse();
});

test('FAQs allow same-day reviewed corrections and reviewed reactivation', function () {
    $faq = Faq::factory()->create(['is_active' => false, 'source_label' => 'Policy owner', 'reviewed_at' => today()]);

    $this->actingAs($this->admin, 'admin')->put(route('admin.faqs.update', $faq->id), [
        'question' => $faq->question, 'answer' => 'Corrected policy.', 'is_active' => 0,
        'source_label' => $faq->source_label, 'reviewed_at' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->postJson(route('admin.faqs.toggle-visibility', $faq->id))->assertOk();

    expect($faq->fresh()->is_active)->toBeTrue()->and($faq->fresh()->answer)->toBe('Corrected policy.');
});

test('active FAQ corrections accept a same-day review but reject an old one', function (string $reviewDate, bool $valid) {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
    $faq = Faq::factory()->create(['is_active' => true, 'source_label' => 'Policy owner', 'reviewed_at' => '2026-10-22']);

    $response = $this->actingAs($this->admin, 'admin')->put(route('admin.faqs.update', $faq->id), [
        'question' => $faq->question, 'answer' => 'Reviewed correction.', 'is_active' => 1,
        'source_label' => $faq->source_label, 'reviewed_at' => $reviewDate,
    ]);

    if ($valid) {
        $response->assertSessionHasNoErrors();
        expect($faq->fresh()->answer)->toBe('Reviewed correction.');
    } else {
        $response->assertSessionHasErrors('reviewed_at');
        expect($faq->fresh()->answer)->toBe($faq->answer);
    }
})->with([['2026-10-22', true], ['2026-10-21', false]]);

test('the list filters by status for live search and shows review and index state', function () {
    KnowledgeArticle::factory()->create(['title' => 'Citable guidance', 'destination_id' => $this->destination->id]);
    KnowledgeArticle::factory()->draft()->create(['title' => 'Draft guidance', 'destination_id' => $this->destination->id]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.knowledge.index', ['visibility' => 'citable']), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('listings-results', false)->assertSee('Citable guidance')->assertDontSee('Draft guidance')
        ->assertSee('Index stale');
});

test('a new SunnyBot FAQ needs a responsible source and review date', function () {
    $faq = ['question' => 'Do you accept GCash?', 'answer' => 'Yes.', 'is_active' => 1];

    $this->actingAs($this->admin, 'admin')->post(route('admin.faqs.store'), $faq)->assertSessionHasErrors(['source_label', 'reviewed_at']);
    $this->actingAs($this->admin, 'admin')->post(route('admin.faqs.store'), [...$faq, 'is_active' => 0])->assertSessionHasNoErrors();

    expect(Faq::sole()->is_active)->toBeFalse();
});

test('a legacy FAQ without review metadata can still be edited when its answer is unchanged', function () {
    $faq = Faq::create(['question' => 'Legacy question?', 'answer' => 'Legacy answer.', 'is_active' => true, 'sort_order' => 0]);

    $this->actingAs($this->admin, 'admin')->put(route('admin.faqs.update', $faq->id), [
        'question' => 'Legacy question?', 'answer' => 'Legacy answer.', 'category' => 'Policies', 'is_active' => 1,
    ])->assertSessionHasNoErrors();

    expect($faq->fresh()->category)->toBe('Policies')->and($faq->fresh()->isReviewed())->toBeFalse();
});
