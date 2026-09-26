<?php

use App\Models\AdminModel;
use App\Models\RecommendationHit;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;

beforeEach(function () {
    $this->admin = AdminModel::create([
        'name' => 'Eval Admin',
        'email' => 'eval-admin-'.uniqid().'@sunnytrips.com',
        'password' => 'password',
    ]);
});

it('requires admin auth', function () {
    $this->get(route('admin.evaluation.index'))->assertRedirect();
});

it('renders empty states with no data', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.evaluation.index'))
        ->assertOk()
        ->assertSee('AI Evaluation')
        ->assertSee('Hit Rate@5', false)
        ->assertSee('No tracked sessions yet')
        ->assertSee('No labeled eval reviews yet')
        ->assertSee('Chatbot offline retrieval eval');
});

it('shows hitrate rows and verdicts', function () {
    $user = User::factory()->create();

    RecommendationHit::create([
        'user_id' => $user->id,
        'session_token' => 'sess-hit-1',
        'mode' => RecommendationHit::MODE_AI,
        'entity_type' => RecommendationHit::TYPE_IMPRESSION,
    ]);
    RecommendationHit::create([
        'user_id' => $user->id,
        'session_token' => 'sess-hit-1',
        'mode' => RecommendationHit::MODE_AI,
        'entity_type' => RecommendationHit::TYPE_HOTEL,
        'entity_id' => 1,
        'rank' => 2,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.evaluation.index'))
        ->assertOk()
        ->assertSee('HIT')
        ->assertSee('ai/hotel #1 r2', false)
        ->assertSee('1.0000');
});

it('shows sentiment matrix when labeled reviews exist', function () {
    $room = RoomType::factory()->create();

    Review::create([
        'reviewable_type' => (new RoomType)->getMorphClass(),
        'reviewable_id' => $room->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'is_published' => true,
        'sentiment' => Review::SENTIMENT_POSITIVE,
        'ground_truth_sentiment' => Review::SENTIMENT_POSITIVE,
        'rating' => 5,
        'comment' => 'Loved it',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.evaluation.index'))
        ->assertOk()
        ->assertSee('Sentiment F1')
        ->assertSee('Actual \\ Predicted', false);
});
