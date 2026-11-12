<?php

use App\Jobs\RegenerateEmbedding;
use App\Jobs\UpdateEntityReviewSummaryJob;
use App\Models\ActivityModel;
use App\Models\AdminModel;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\GeminiService;
use App\Services\Recommendations\RecommendationExplainer;
use Illuminate\Support\Facades\Queue;

it('queues a retry when an admin save gets no embedding', function () {
    Queue::fake();
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateEmbedding')->andReturn(null));
    $admin = AdminModel::create(['name' => 'Test Admin', 'email' => 'admin@embeddingtest.com', 'password' => 'password']);

    $this->actingAs($admin, 'admin')->post(route('admin.addons.store'), [
        'destination_id' => DestinationModel::factory()->create()->id, 'name' => 'Snorkel set', 'type' => 'Equipment',
    ])->assertRedirect();

    Queue::assertPushed(RegenerateEmbedding::class, fn (RegenerateEmbedding $job) => $job->type === 'addon');
});

it('completes onboarding with a placeholder and queues a profile retry when Gemini is down', function () {
    Queue::fake();
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateEmbedding')->andReturn(null));
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.store'), ['vibes' => ['Beachfront'], 'destination' => 'Boracay'])
        ->assertRedirect(route('onboarding.processing'));

    expect($user->fresh()->preferences_embedding)->not->toBeNull();
    Queue::assertPushed(RegenerateEmbedding::class, fn (RegenerateEmbedding $job) => $job->type === 'user' && $job->id === $user->id);
});

it('embeds the listing as it is now once Gemini recovers', function () {
    $activity = ActivityModel::factory()->create(['activity_name' => 'Old name', 'embedding' => null]);
    $activity->update(['activity_name' => 'Sunset Kayak']);
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateEmbedding')->once()
        ->withArgs(fn (string $text, string $task, ?string $title) => str_contains($text, 'Sunset Kayak') && $title === 'Sunset Kayak')
        ->andReturn(array_fill(0, 3072, 0.02)));

    RegenerateEmbedding::dispatchSync('activity', $activity->id);

    expect($activity->fresh()->embedding)->toStartWith('[0.02,');
});

it('waits and tries again while Gemini is still unavailable', function () {
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateEmbedding')->andReturn(null));
    $activity = ActivityModel::factory()->create();
    $job = (new RegenerateEmbedding('activity', $activity->id))->withFakeQueueInteractions();

    $job->handle(app(GeminiService::class), app(RecommendationExplainer::class));

    $job->assertReleased(delay: 60);
});

it('replaces a placeholder profile and its saved explanations once Gemini recovers', function () {
    $user = User::factory()->create(['preferences_embedding' => '['.implode(',', array_fill(0, 3072, 0)).']']);
    $preference = UserPreference::create(['user_id' => $user->id, 'destination' => 'Boracay', 'vibes' => ['Beachfront']]);
    $preference->forceFill(['recommendation_explanations' => ['1' => ['hotels' => 'Old', 'activities' => 'Old']]])->save();
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateEmbedding')->andReturn(array_fill(0, 3072, 0.02)));

    RegenerateEmbedding::dispatchSync('user', $user->id);

    expect($user->fresh()->preferences_embedding)->toStartWith('[0.02,')
        ->and($preference->fresh()->recommendation_explanations)->toBeNull();
});

it('records the source hash and model so a stale sweep does not embed the listing again', function () {
    $activity = ActivityModel::factory()->create(['activity_name' => 'Reef Walk', 'embedding' => null]);
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateEmbedding')->andReturn(array_fill(0, 3072, 0.02)));

    RegenerateEmbedding::dispatchSync('activity', $activity->id);

    $fresh = $activity->fresh();
    $model = config('services.gemini.embedding_model');
    expect($fresh->embedding_model)->toBe($model)
        ->and($fresh->embedding_source_hash)->toBe(sha1(app(GeminiService::class)->buildActivityEmbeddingText($fresh->load('destination'), $fresh->destination?->name).$model))
        ->and($fresh->embedded_at)->not->toBeNull();
});

it('refreshes a hotel vector after its review summary changes', function () {
    Queue::fake([RegenerateEmbedding::class]);
    $hotel = HotelModel::factory()->create();

    (new UpdateEntityReviewSummaryJob('hotel', $hotel->id, $hotel->hotel_name))->handle(app(GeminiService::class));

    Queue::assertPushed(RegenerateEmbedding::class, fn (RegenerateEmbedding $job) => $job->type === 'hotel' && $job->id === $hotel->id);
});

it('embeds an FAQ through the retry job', function () {
    $faq = Faq::factory()->create(['question' => 'Do you accept GCash?', 'embedding' => null]);
    $this->partialMock(GeminiService::class, fn ($mock) => $mock->shouldReceive('generateEmbedding')->once()
        ->withArgs(fn (string $text, string $task, ?string $title) => str_contains($text, 'GCash') && $title === 'Do you accept GCash?')
        ->andReturn(array_fill(0, 3072, 0.02)));

    RegenerateEmbedding::dispatchSync('faq', $faq->id);

    expect($faq->fresh()->embedding)->toStartWith('[0.02,')->and($faq->fresh()->embedding_model)->toBe(config('services.gemini.embedding_model'));
});
