<?php

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\CartItem;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\TripPlan;
use App\Services\Chat\ChatbotTripPlanService;
use App\Services\TripPlannerService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->user = onboardedUser();
    $this->destination = DestinationModel::factory()->create();
    $this->activity = ActivityModel::factory()->create([
        'destination_id' => $this->destination->id, 'activity_name' => 'Island Hopping Tour', 'capacity' => 'Up to 10 guests', 'rate' => '₱500/person',
    ]);
    $this->actingAs($this->user);
});

it('names the activity and its limit in the capacity message', function () {
    expect($this->activity->fitsParty(10))->toBeTrue()->and($this->activity->fitsParty(11))->toBeFalse()
        ->and($this->activity->capacityMessage(12))->toStartWith('Island Hopping Tour takes up to 10 guests per booking; your group has 12.');
});

it('rejects adding an activity to the basket for more travelers than it takes', function () {
    $this->postJson(route('cart.add'), ['item_type' => 'activity', 'item_id' => $this->activity->id, 'selected_pax' => 12])
        ->assertUnprocessable()->assertJsonPath('success', false)
        ->assertJson(fn ($json) => $json->where('message', fn ($message) => str_contains($message, 'takes up to 10 guests'))->etc());
    expect(CartItem::count())->toBe(0);

    $this->postJson(route('cart.add'), ['item_type' => 'activity', 'item_id' => $this->activity->id, 'selected_pax' => 10])->assertOk();
});

it('rejects a repeat add that would stack the basket line past capacity', function () {
    $this->postJson(route('cart.add'), ['item_type' => 'activity', 'item_id' => $this->activity->id, 'selected_pax' => 6])->assertOk();

    $this->postJson(route('cart.add'), ['item_type' => 'activity', 'item_id' => $this->activity->id, 'quantity' => 6])->assertUnprocessable();

    expect(CartItem::sole()->selected_pax)->toBe(6);
});

it('rejects raising a basket line above the activity capacity', function () {
    $line = CartItem::create(['user_id' => $this->user->id, 'item_type' => 'activity', 'item_id' => $this->activity->id, 'quantity' => 4, 'selected_pax' => 4, 'is_selected' => true]);

    $this->patchJson(route('cart.update', $line->id), ['selected_pax' => 11])->assertUnprocessable()->assertJsonValidationErrors('selected_pax');

    expect($line->fresh()->selected_pax)->toBe(4);
});

it('refuses to book a stale over-capacity basket line at checkout', function () {
    CartItem::create(['user_id' => $this->user->id, 'item_type' => 'activity', 'item_id' => $this->activity->id, 'quantity' => 15, 'selected_pax' => 15, 'is_selected' => true]);

    $this->postJson(route('checkout.process'), ['contact_name' => 'Ana Reyes', 'contact_email' => $this->user->email, 'contact_phone' => '09171234567'])
        ->assertUnprocessable()->assertJsonValidationErrors('items');

    expect(Booking::count())->toBe(0);
});

it('names the activity when the Trip Planner party is too large for it', function () {
    $plan = TripPlan::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->destination->id, 'pax' => 12]);

    expect(fn () => app(TripPlannerService::class)->add($plan, ['item_type' => 'activity', 'item_id' => $this->activity->id, 'pax' => 12]))
        ->toThrow(ValidationException::class, 'Island Hopping Tour takes up to 10 guests');
});

it('blocks building a schedule that holds an over-capacity activity', function () {
    $plan = TripPlan::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->destination->id, 'pax' => 12]);
    $plan->items()->create(['item_type' => 'activity', 'item_id' => $this->activity->id, 'options' => ['pax' => 12, 'quantity' => 1]]);

    expect(fn () => app(TripPlannerService::class)->prepareForSchedule($plan))->toThrow(ValidationException::class, 'takes up to 10 guests');
});

it('saves a chatbot draft without the activity that is too small for the group, with the reason', function () {
    $session = ChatSession::create(['session_token' => ChatSession::generateToken(), 'user_id' => $this->user->id]);
    $suggestion = app(ChatbotTripPlanService::class)->suggestion(['activities' => [['id' => $this->activity->id, 'activity_name' => $this->activity->activity_name, 'day' => 1]]],
        ['destination_id' => $this->destination->id, 'destination_name' => $this->destination->name, 'days' => 3, 'pax' => 12]);
    ChatMessage::create(['chat_session_id' => $session->id, 'sender' => 'bot', 'message' => 'Suggested itinerary',
        'context_data' => ['itinerary_suggestions' => [$suggestion['id'] => $suggestion]]]);

    $saved = $this->postJson('/trip-planner/chatbot-plans', ['suggestion_id' => $suggestion['id']])->assertCreated();

    $plan = TripPlan::findOrFail($saved->json('data.id'));
    $choice = collect($plan->itineraries()->sole()->snapshot['choices'])->firstWhere('id', $this->activity->id);
    expect($plan->items)->toHaveCount(0)
        ->and($choice['available'])->toBeFalse()
        ->and($choice['notice'])->toContain('takes up to 10 guests');
});
