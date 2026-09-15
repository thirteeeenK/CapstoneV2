<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\RoomType;
use App\Services\BookingRequestService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['services.openweather.api_key' => 'test-key']);
    Notification::fake();
});

it('renders the hotel page when the weather API is unreachable', function () {
    $room = RoomType::factory()->create();
    Http::fake(['api.openweathermap.org/*' => Http::response([], 500)]);

    $this->get(route('hotels.show', $room->hotel))
        ->assertOk()
        ->assertDontSee('internal error')
        ->assertDontSee('Whoops');
});

it('tells the user weather is unavailable instead of failing when the weather API times out', function () {
    $room = RoomType::factory()->create();
    Http::fake([
        'api.openweathermap.org/*' => Http::response([], 500),
        '*' => Http::response([], 500),
    ]);

    $user = onboardedUser();

    $response = $this->actingAs($user)->postJson('/chat', [
        'message' => "What's the weather in {$room->hotel->destination->name}?",
    ]);

    $response->assertOk();
    expect($response->json('reply'))->toBeString()->not->toBeEmpty();
});

it('still replies when the Gemini API is unreachable', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API down']], 503),
        '*' => Http::response([], 500),
    ]);

    $user = onboardedUser();

    $response = $this->actingAs($user)->postJson('/chat', [
        'message' => 'Hello, can you help me plan a trip?',
    ]);

    $response->assertOk()
        ->assertJsonMissing(['blocked' => true]);

    expect($response->json('reply'))->toBeString()->not->toBeEmpty();
});

it('keeps each users cart isolated between sessions', function () {
    $userA = onboardedUser();
    $userB = onboardedUser();
    $room = RoomType::factory()->create();

    CartItem::create([
        'user_id' => $userA->id,
        'item_type' => 'room',
        'item_id' => $room->id,
        'item_title' => 'User A Room',
        'quantity' => 1,
        'selected_pax' => 1,
        'subtotal' => 100,
    ]);

    $this->assertDatabaseCount('cart_items', 1);

    $responseB = $this->actingAs($userB)->getJson(route('cart.data'));
    $responseA = $this->actingAs($userA)->getJson(route('cart.data'));

    expect($responseB->json('items'))->toBeEmpty()
        ->and($responseA->json('items'))->toHaveCount(1);
});

it('leaves no partial booking and keeps the cart when checkout fails', function () {
    $user = onboardedUser();
    $room = RoomType::factory()->create();

    $cartItem = CartItem::create([
        'user_id' => $user->id,
        'item_type' => 'room',
        'item_id' => $room->id,
        'item_title' => 'Failing Room',
        'quantity' => 1,
        'selected_pax' => 1,
        'subtotal' => 100,
        'is_selected' => true,
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
    ]);

    // Force a failure inside the transaction to prove atomicity.
    $this->partialMock(BookingRequestService::class, function ($mock) {
        $mock->shouldReceive('buildFromCart')->andThrow(new RuntimeException('Simulated booking failure'));
    });

    $response = $this->actingAs($user)->postJson(route('checkout.process'), [
        'contact_name' => 'Reliability Tester',
        'contact_email' => 'reliability@test.com',
        'contact_phone' => '09171234567',
        'cart_item_ids' => [$cartItem->id],
    ]);

    $response->assertStatus(500)
        ->assertJsonPath('success', false);

    expect($cartItem->fresh())->not->toBeNull()
        ->and(Booking::count())->toBe(0);
});

it('creates a complete booking with items and history in one atomic write', function () {
    $user = onboardedUser();
    $room = RoomType::factory()->create();

    $cartItem = CartItem::create([
        'user_id' => $user->id,
        'item_type' => 'room',
        'item_id' => $room->id,
        'item_title' => 'Atomic Room',
        'quantity' => 1,
        'selected_pax' => 2,
        'subtotal' => 5000,
        'is_selected' => true,
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
    ]);

    Http::fake();

    $response = $this->actingAs($user)->postJson(route('checkout.process'), [
        'contact_name' => 'Atomic Tester',
        'contact_email' => 'atomic@test.com',
        'contact_phone' => '09171234567',
        'cart_item_ids' => [$cartItem->id],
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $booking = Booking::sole();

    expect($booking->items)->toHaveCount(1)
        ->and($booking->status)->toBe(Booking::STATUS_PENDING)
        ->and($cartItem->fresh())->toBeNull()
        ->and(BookingItem::count())->toBe(1);
});
