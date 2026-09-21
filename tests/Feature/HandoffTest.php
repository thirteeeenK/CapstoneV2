<?php

use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => [
                'values' => array_fill(0, 3072, 0.01),
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

    $destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Handoff Beach Resort',
        'destination_id' => $destination->id,
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Handoff Deluxe Room',
        'base_price' => 2500.00,
        'embedding' => '['.implode(',', array_fill(0, 3072, '0.01')).']',
    ]);
});

function pendingHandoffSession(): ChatSession
{
    $session = ChatSession::create(['session_token' => ChatSession::generateToken()]);
    SupportInquiry::create([
        'ticket_number' => 'TKT-HO-'.strtoupper(substr(md5($session->session_token), 0, 6)),
        'chat_session_id' => $session->id,
        'status' => SupportInquiry::STATUS_PENDING,
        'requested_at' => now(),
    ]);

    return $session;
}

test('pending ticket allows an AI reply while awaiting an agent', function () {
    $session = pendingHandoffSession();

    $response = $this->postJson('/chat', [
        'message' => 'recommend a room in Boracay',
        'session_token' => $session->session_token,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'control' => 'ai',
            'handoff_status' => SupportInquiry::STATUS_PENDING,
        ]);
    expect($response->json('reply'))->not->toContain('An administrator will be with you shortly');
    expect(SupportInquiry::where('chat_session_id', $session->id)->first()->status)
        ->toBe(SupportInquiry::STATUS_PENDING);
});

test('pending user and bot messages are persisted for the future admin', function () {
    $session = pendingHandoffSession();

    $this->postJson('/chat', [
        'message' => 'recommend a room in Boracay',
        'session_token' => $session->session_token,
    ])->assertOk();

    expect($session->messages()->where('sender', 'user')->count())->toBe(1);
    expect($session->messages()->where('sender', 'bot')->count())->toBe(1);
});

test('transitioning from pending to human-active stops AI replies', function () {
    $session = pendingHandoffSession();

    $this->postJson('/chat', [
        'message' => 'recommend a room in Boracay',
        'session_token' => $session->session_token,
    ])->assertJson(['control' => 'ai']);

    SupportInquiry::where('chat_session_id', $session->id)
        ->update(['status' => SupportInquiry::STATUS_HUMAN_ACTIVE]);

    $response = $this->postJson('/chat', [
        'message' => 'are you the admin?',
        'session_token' => $session->session_token,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'human_support_active',
            'control' => 'admin',
        ])
        ->assertJsonMissingPath('reply');
});

test('cancelling a pending ticket keeps AI working', function () {
    $session = pendingHandoffSession();

    $this->postJson('/chat/handoff/cancel', [
        'session_token' => $session->session_token,
    ])->assertOk();

    $response = $this->postJson('/chat', [
        'message' => 'recommend a room in Boracay',
        'session_token' => $session->session_token,
    ]);

    $response->assertOk()->assertJson(['control' => 'ai']);
    expect($response->json('handoff_status'))
        ->not->toBe(SupportInquiry::STATUS_PENDING);
});
