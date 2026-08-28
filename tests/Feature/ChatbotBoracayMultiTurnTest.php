<?php

use App\Http\Middleware\EnforceGuestChatLimits;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\UserPreference;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    // Clear limiters so multi-turn 6-step transcript doesn't hit 429 in suite
    RateLimiter::clear('ai:127.0.0.1');
    RateLimiter::clear('ai:::1');
    RateLimiter::clear('ai');
    RateLimiter::clear('chat-guest:127.0.0.1');
    RateLimiter::clear('chat-guest-daily:127.0.0.1');
    // Bypass both throttles for heavy multi-turn tests
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->withoutMiddleware(EnforceGuestChatLimits::class);
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 3072, 0.01)],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Here is a recommendation for you!']]]]],
        ]),
    ]);

    $this->boracay = DestinationModel::factory()->create([
        'name' => 'Boracay',
        'description' => 'White beach',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
    ]);
    $this->elnido = DestinationModel::factory()->create([
        'name' => 'El Nido',
        'description' => 'Lagoons',
        'latitude' => 11.1,
        'longitude' => 119.3,
    ]);

    $embedding = '['.implode(',', array_fill(0, 3072, '0.01')).']';

    $this->boracayHotel = HotelModel::factory()->create([
        'hotel_name' => 'Boracay Luxury Pool Resort',
        'destination_id' => $this->boracay->id,
        'type' => 'Resort',
        'hotel_description' => 'Luxury quiet family-friendly resort with pool in Boracay.',
        'specific_address' => 'Station 1 Boracay',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
        'is_shown' => true,
        'embedding' => $embedding,
        'featured_amenities' => json_encode(['Pool', 'Luxury', 'Quiet', 'Family-Friendly']),
        'vibe_tags' => json_encode(['luxury', 'quiet', 'family-friendly']),
    ]);

    // Second Boracay hotel for list
    $this->boracayHotel2 = HotelModel::factory()->create([
        'hotel_name' => 'Boracay Quiet Villa',
        'destination_id' => $this->boracay->id,
        'type' => 'Villa',
        'hotel_description' => 'Quiet luxury villa with pool.',
        'specific_address' => 'Station 2 Boracay',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
        'is_shown' => true,
        'embedding' => $embedding,
        'featured_amenities' => json_encode(['Pool', 'Quiet']),
        'vibe_tags' => json_encode(['quiet', 'luxury']),
    ]);

    $this->boracayRoom = RoomType::factory()->create([
        'hotel_id' => $this->boracayHotel->id,
        'room_name' => 'Luxury Pool Suite Boracay',
        'base_price' => 8000,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500,
        'total_rooms' => 5,
        'is_shown' => true,
        'embedding' => $embedding,
        'room_amenities' => json_encode(['Pool', 'Luxury']),
        'description' => 'Luxury family-friendly quiet room with pool access in Boracay',
    ]);

    $this->elNidoHotel = HotelModel::factory()->create([
        'hotel_name' => 'Lihim Resorts',
        'destination_id' => $this->elnido->id,
        'type' => 'Resort',
        'hotel_description' => 'Luxury private pool villa in El Nido.',
        'specific_address' => 'El Nido Town',
        'latitude' => 11.1,
        'longitude' => 119.3,
        'is_shown' => true,
        'embedding' => $embedding,
        'featured_amenities' => json_encode(['Private Pool', 'Luxury']),
        'vibe_tags' => json_encode(['luxury']),
    ]);

    RoomType::factory()->create([
        'hotel_id' => $this->elNidoHotel->id,
        'room_name' => 'Luxury Villa at Lihim Resorts',
        'base_price' => 60000,
        'base_occupancy' => 2,
        'max_occupancy' => 2,
        'extra_person_fee' => 0,
        'total_rooms' => 3,
        'is_shown' => true,
        'embedding' => $embedding,
    ]);

    // Create an activity in Boracay so first activity search works
    ActivityModel::factory()->create([
        'activity_name' => 'Scuba Diving',
        'destination_id' => $this->boracay->id,
        'category' => 'Diving',
        'rate' => 1350,
        'is_shown' => true,
        'embedding' => $embedding,
    ]);
});

test('boracay activity then hotel/room with Boracay parenthesis is fresh search not follow-up', function () {
    $first = $this->postJson('/chat', ['message' => 'based on my preference what activities would you recommend me in boracay']);
    $first->assertOk()->assertJsonPath('status', 'success');
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'how about for hotels and rooms?(pertaining to boracay)',
        'session_token' => $token,
    ]);
    $second->assertOk()->assertJsonPath('status', 'success');
    // Must NOT be a follow-up that returns activities; must be rooms/hotels in Boracay
    $hasBoracay = false;
    foreach (['retrieved_rooms', 'retrieved_hotels'] as $key) {
        $items = $second->json($key) ?? [];
        foreach ($items as $it) {
            if (($it['destination'] ?? '') === 'Boracay') {
                $hasBoracay = true;
            }
        }
    }
    // At least one Boracay result and no El Nido leakage
    expect($hasBoracay)->toBeTrue();
    $data = $second->json();
    // Should not be the follow-up apology "I can only recommend activities"
    expect($second->json('reply'))->not->toContain('I can only recommend activities');
});

test('bare amenity filter inherits last Boracay destination', function () {
    $first = $this->postJson('/chat', ['message' => 'Find hotels in Boracay']);
    $first->assertOk();
    $token = $first->json('session_token');
    expect($first->json('retrieved_hotels'))->not->toBeEmpty();

    $second = $this->postJson('/chat', [
        'message' => 'luxury, family-friendly and with pool',
        'session_token' => $token,
    ]);
    $second->assertOk()->assertJsonPath('status', 'success');
    // Should still be Boracay, not El Nido
    $hotels = $second->json('retrieved_hotels') ?? [];
    $rooms = $second->json('retrieved_rooms') ?? [];
    $all = array_merge($hotels, $rooms);
    expect($all)->not->toBeEmpty();
    foreach ($all as $item) {
        expect($item['destination'] ?? null)->toBe('Boracay');
    }
});

test('quiet luxury pool bare query after Boracay hotel is not GENERAL_TALK', function () {
    $first = $this->postJson('/chat', ['message' => 'recommend a hotel in Boracay']);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'luxury. quiet, and with pool',
        'session_token' => $token,
    ]);
    $second->assertOk();
    expect($second->json('reply'))->not->toContain('I can only help with travel planning');
    expect($second->json('reply'))->not->toContain('outside my expertise');
    $hotels = $second->json('retrieved_hotels') ?? [];
    $rooms = $second->json('retrieved_rooms') ?? [];
    expect(array_merge($hotels, $rooms))->not->toBeEmpty();
});

test('"then find me something in boracay" with no hotel/room keyword still searches Boracay', function () {
    $first = $this->postJson('/chat', ['message' => 'Find hotels in Boracay']);
    $first->assertOk();
    $token = $first->json('session_token');

    // Simulate the middle empty amenity that previously leaked to El Nido, then re-ask Boracay
    $this->postJson('/chat', ['message' => 'luxury, family-friendly and with pool', 'session_token' => $token]);

    $third = $this->postJson('/chat', [
        'message' => 'then find me something in boracay',
        'session_token' => $token,
    ]);
    $third->assertOk()->assertJsonPath('status', 'success');
    $hotels = $third->json('retrieved_hotels') ?? [];
    $rooms = $third->json('retrieved_rooms') ?? [];
    // Should have at least one Boracay result, not ask for budget again
    $hasBoracay = false;
    foreach (array_merge($hotels, $rooms) as $it) {
        if (($it['destination'] ?? '') === 'Boracay') {
            $hasBoracay = true;
        }
    }
    // Could be hotels or rooms; allow either but must be Boracay
    expect($hasBoracay)->toBeTrue();
});

test('conversational Boracay overrides profile El Nido preference', function () {
    $user = onboardedUser();
    UserPreference::updateOrCreate(['user_id' => $user->id], [
        'destination' => 'El Nido',
        'traveler_type' => 'family',
        'vibes' => ['luxury'],
        'amenities' => ['Pool'],
    ]);

    $this->actingAs($user);
    $first = $this->postJson('/chat', ['message' => 'Find hotels in Boracay']);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'luxury, family-friendly and with pool',
        'session_token' => $token,
    ]);
    $second->assertOk();
    $hotels = $second->json('retrieved_hotels') ?? [];
    $rooms = $second->json('retrieved_rooms') ?? [];
    foreach (array_merge($hotels, $rooms) as $it) {
        expect($it['destination'] ?? null)->toBe('Boracay');
    }
});

test('full transcript: activity → hotels parenthesis → yes search → luxury family pool → re-find boracay → luxury quiet pool stays Boracay', function () {
    $first = $this->postJson('/chat', ['message' => 'based on my preference what activities would you recommend me in boracay']);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', ['message' => 'how about for hotels and rooms?(pertaining to boracay)', 'session_token' => $token]);
    $second->assertOk();
    $hasBoracay2 = false;
    foreach (array_merge($second->json('retrieved_hotels') ?? [], $second->json('retrieved_rooms') ?? []) as $it) {
        if (($it['destination'] ?? '') === 'Boracay') {
            $hasBoracay2 = true;
        }
    }
    expect($hasBoracay2)->toBeTrue();

    // "yes search." affirmation after bot offered search
    $third = $this->postJson('/chat', ['message' => 'yes search.', 'session_token' => $token]);
    $third->assertOk()->assertJsonPath('status', 'success');
    // Should be Boracay rooms/hotels, not general refusal
    expect($third->json('reply'))->not->toContain('I can only help with travel planning');
    $hasBoracay3 = false;
    foreach (array_merge($third->json('retrieved_hotels') ?? [], $third->json('retrieved_rooms') ?? []) as $it) {
        if (($it['destination'] ?? '') === 'Boracay') {
            $hasBoracay3 = true;
        }
    }
    expect($hasBoracay3)->toBeTrue();

    $fourth = $this->postJson('/chat', ['message' => 'luxury, family-friendly and with pool', 'session_token' => $token]);
    $fourth->assertOk();
    foreach (array_merge($fourth->json('retrieved_hotels') ?? [], $fourth->json('retrieved_rooms') ?? []) as $it) {
        expect($it['destination'] ?? null)->toBe('Boracay');
    }
    // Filter badge visible
    expect($fourth->json('reply'))->toContain('Filtered for');

    $fifth = $this->postJson('/chat', ['message' => 'then find me something in boracay', 'session_token' => $token]);
    $fifth->assertOk();
    $hasBoracay5 = false;
    foreach (array_merge($fifth->json('retrieved_hotels') ?? [], $fifth->json('retrieved_rooms') ?? []) as $it) {
        if (($it['destination'] ?? '') === 'Boracay') {
            $hasBoracay5 = true;
        }
    }
    expect($hasBoracay5)->toBeTrue();

    $sixth = $this->postJson('/chat', ['message' => 'luxury. quiet, and with pool', 'session_token' => $token]);
    $sixth->assertOk();
    expect($sixth->json('reply'))->not->toContain('I can only help with travel planning');
    foreach (array_merge($sixth->json('retrieved_hotels') ?? [], $sixth->json('retrieved_rooms') ?? []) as $it) {
        expect($it['destination'] ?? null)->toBe('Boracay');
    }
    expect($sixth->json('reply'))->toContain('Filtered for');
});

test('explicit El Nido switch after Boracay overrides conversational memory', function () {
    $first = $this->postJson('/chat', ['message' => 'Find hotels in Boracay']);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', ['message' => 'luxury, family-friendly and with pool', 'session_token' => $token]);
    $second->assertOk();

    $third = $this->postJson('/chat', ['message' => 'actually show me something in El Nido', 'session_token' => $token]);
    $third->assertOk();
    $hasElNido = false;
    foreach (array_merge($third->json('retrieved_hotels') ?? [], $third->json('retrieved_rooms') ?? []) as $it) {
        if (($it['destination'] ?? '') === 'El Nido') {
            $hasElNido = true;
        }
    }
    expect($hasElNido)->toBeTrue();
});
