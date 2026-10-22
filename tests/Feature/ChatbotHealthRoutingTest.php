<?php

use App\Models\ActivityModel;
use App\Models\ChatbotAbuseReport;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\SupportInquiry;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
        '*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Here is a recommendation for you!']]]]],
        ]),
    ]);

    $destination = DestinationModel::factory()->create(['name' => 'Boracay']);

    // A catalog name whose fragments ("ain", "pains") collide with symptom
    // wording — the collision BUG 8 reported.
    ActivityModel::factory()->create([
        'activity_name' => 'Mountain Trail Pains Relief Massage',
        'destination_id' => $destination->id,
        'embedding' => '['.implode(',', array_fill(0, 3072, '0.01')).']',
    ]);
});

test('an urgent symptom message gets emergency direction and no catalog cards', function () {
    $response = $this->postJson('/chat', [
        'message' => 'my friend has chest pains and cannot breathe, what do we do',
    ]);

    $response->assertOk()->assertJson(['status' => 'success']);

    $reply = $response->json('reply');
    expect($reply)->toContain('911')
        ->and($reply)->toContain('emergency')
        ->and($reply)->not->toContain('Massage');

    // No diagnosis, no trip promotion, no invented hotline digits.
    expect($reply)->not->toMatch('/\b(?:you (?:probably|likely|may) have|diagnos)/i');
    expect($response->json('activities'))->toBeEmpty();
    expect($response->json('rooms'))->toBeEmpty();
    expect($response->json('retrieval_outcome.reason'))->toBe('health_concern');
});

test('a non-urgent medical question gets a capability boundary, not an activity', function () {
    $response = $this->postJson('/chat', [
        'message' => 'I have a bad headache and stomach pains, what should I take?',
    ]);

    $reply = $response->json('reply');
    expect($reply)->toContain("can't help with medical")
        ->and($reply)->not->toContain('Massage');
    expect($response->json('activities'))->toBeEmpty();
});

test('a crisis disclosure is never treated as abuse', function () {
    $user = User::factory()->create(['chatbot_flag_count' => 0, 'preferences_embedding' => null]);

    $response = $this->actingAs($user)->postJson('/chat', [
        'message' => 'I want to kill myself',
    ]);

    $response->assertOk();
    $reply = $response->json('reply');

    expect($reply)->toContain('911')
        ->and($reply)->not->toContain('community guidelines')
        ->and($reply)->not->toContain('logged for administrator review');

    expect(ChatbotAbuseReport::count())->toBe(0);
    expect($user->fresh()->chatbot_flag_count)->toBe(0);
    expect($user->fresh()->isBanned())->toBeFalse();
});

test('health logistics still reach the normal travel pipeline', function () {
    foreach ([
        'is there a hospital near Boracay?',
        'do you have wheelchair accessible rooms in Boracay?',
        'what is the emergency contact number of the hotel',
    ] as $message) {
        $response = $this->postJson('/chat', ['message' => $message]);

        $response->assertOk();
        expect($response->json('retrieval_outcome.reason'))->not->toBe('health_concern');
    }
});

test('active human support still wins over health interception', function () {
    $session = ChatSession::create(['session_token' => ChatSession::generateToken()]);
    SupportInquiry::create([
        'ticket_number' => 'TKT-HC-001',
        'chat_session_id' => $session->id,
        'status' => SupportInquiry::STATUS_HUMAN_ACTIVE,
        'requested_at' => now(),
    ]);

    $this->postJson('/chat', [
        'message' => 'I have a bad headache, what should I take?',
        'session_token' => $session->session_token,
    ])->assertOk()->assertJson([
        'status' => 'human_support_active',
        'control' => 'admin',
    ])->assertJsonMissingPath('reply');
});

test('urgent symptoms take priority over health logistics in the same message', function (string $message) {
    $this->postJson('/chat', ['message' => $message])->assertOk()
        ->assertJsonPath('retrieval_outcome.reason', 'health_concern');
})->with([
    'I have chest pains and use a wheelchair',
    'I have chest pains, where is the nearest hospital?',
]);
