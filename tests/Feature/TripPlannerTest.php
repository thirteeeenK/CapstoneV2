<?php

use App\Models\ActivityModel;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\TouristAttraction;
use App\Models\TripItinerary;
use App\Models\TripPlan;
use App\Notifications\TripItineraryReady;
use App\Services\BookingRequestService;
use App\Services\ItineraryService;
use App\Services\RoutingService;
use App\Services\TripPlannerService;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-13 10:00', 'Asia/Manila'));
    $this->user = onboardedUser();
    $this->actingAs($this->user);
    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id, 'latitude' => 11.96, 'longitude' => 121.927]);
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'is_shown' => true, 'total_rooms' => 5, 'max_occupancy' => 4]);
    $this->plan = TripPlan::factory()->create(['user_id' => $this->user->id, 'destination_id' => $this->destination->id,
        'start_date' => '2026-10-14', 'end_date' => '2026-10-17']);
    $this->partialMock(RoutingService::class, function ($mock) {
        $mock->shouldReceive('matrix')->andReturnUsing(function ($sources, $targets) {
            $distances = $durations = [];
            foreach ($sources as $i => $source) {
                foreach ($targets as $j => $target) {
                    $valid = isset($source['lat'], $target['lat']);
                    $distances[$i][$j] = $valid ? ($i === $j ? 0 : 1.25) : null;
                    $durations[$i][$j] = $valid ? ($i === $j ? 0 : 600) : null;
                }
            }

            return ['distances' => $distances, 'durations' => $durations, 'mode' => 'walking'];
        });
        $mock->shouldReceive('route')->andReturn(['status' => 'ok', 'geometry' => ['type' => 'LineString', 'coordinates' => [[121.927, 11.96], [121.925, 11.965]]]]);
    });
    $raw = ['_fetched_at' => now()->toIso8601String(), 'list' => []];
    for ($time = Carbon::parse('2026-10-14 00:00', 'Asia/Manila'); $time->lt(Carbon::parse('2026-10-18 00:00', 'Asia/Manila')); $time->addHours(3)) {
        $rainy = $time->toDateString() === '2026-10-14';
        $raw['list'][] = ['dt' => $time->timestamp, 'main' => ['temp' => 28], 'weather' => [['id' => $rainy ? 500 : 800, 'description' => $rainy ? 'light rain' : 'clear sky']],
            'rain' => ['3h' => $rainy ? 6 : 0], 'wind' => ['speed' => 3], 'pop' => $rainy ? 0.95 : 0.1];
    }
    $this->partialMock(WeatherService::class, fn ($mock) => $mock->shouldReceive('forecastForDestination')->andReturn($raw));
});

function plannerAdd(TripPlan $plan, string $type, int $id, array $options = []): void
{
    app(TripPlannerService::class)->add($plan->fresh(), ['item_type' => $type, 'item_id' => $id] + $options);
}

function plannerActivity(DestinationModel $destination, array $attributes = []): ActivityModel
{
    return ActivityModel::factory()->create($attributes + ['destination_id' => $destination->id, 'latitude' => 11.965, 'longitude' => 121.925,
        'duration' => '1 hour', 'scheduling' => ['duration_minutes' => 60, 'opens_at' => '09:00', 'closes_at' => '18:00', 'environment' => 'outdoor']]);
}

it('keeps saved planner selections separate from the existing basket and deduplicates choices', function () {
    $this->getJson(route('trip-planner.show', $this->plan))->assertJsonPath('data.start_date', '2026-10-14')->assertJsonPath('data.end_date', '2026-10-17');
    $this->postJson(route('trip-planner.add', $this->plan), ['item_type' => 'room', 'item_id' => $this->room->id])->assertOk();
    $this->postJson(route('trip-planner.add', $this->plan), ['item_type' => 'room', 'item_id' => $this->room->id])->assertOk();
    expect($this->plan->items()->count())->toBe(1)->and(CartItem::count())->toBe(0);
    $this->get(route('trip-planner.index'))->assertOk()->assertSee('Plan your island days');
});

it('protects another users plan and its documents and rejects cross destination and hidden items', function () {
    $other = TripPlan::factory()->create();
    $this->getJson(route('trip-planner.show', $other))->assertNotFound();
    $this->postJson(route('trip-planner.generate', $other))->assertNotFound();
    $itinerary = TripItinerary::factory()->create(['trip_plan_id' => $other->id]);
    $this->get(route('trip-planner.document', [$other, $itinerary]))->assertNotFound();
    $foreign = ActivityModel::factory()->create();
    $this->postJson(route('trip-planner.add', $this->plan), ['item_type' => 'activity', 'item_id' => $foreign->id])->assertUnprocessable();
    $this->room->update(['is_shown' => false]);
    $this->postJson(route('trip-planner.add', $this->plan), ['item_type' => 'room', 'item_id' => $this->room->id])->assertUnprocessable();
});

it('puts outdoor visits on a clear day and uses real route evidence in the strategic explanation', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $outdoor = plannerActivity($this->destination);
    $indoor = plannerActivity($this->destination, ['scheduling' => ['duration_minutes' => 60, 'opens_at' => '09:00', 'closes_at' => '18:00', 'environment' => 'indoor']]);
    plannerAdd($this->plan, 'activity', $outdoor->id);
    plannerAdd($this->plan, 'activity', $indoor->id);
    $response = $this->postJson(route('trip-planner.generate', $this->plan))->assertOk();
    $snapshot = $response->json('data.snapshot');
    $stops = collect($snapshot['days'])->flatMap(fn ($day) => $day['stops']);
    expect($stops->firstWhere('item_id', $outdoor->id)['date'])->toBe('2026-10-15')
        ->and($stops->firstWhere('item_id', $indoor->id)['date'])->toBe('2026-10-14')
        ->and($stops->firstWhere('item_id', $outdoor->id)['reason'])->toContain('1.25 km', '10 minutes', 'forecast', '2026-10-14')
        ->and($snapshot['unscheduled'])->toBeEmpty()
        ->and($snapshot['days'][1]['legs'])->toHaveCount(2)
        ->and($snapshot['days'][1]['legs'][0]['geometry']['coordinates'])->toHaveCount(2);
});

it('retains unscheduled choices instead of inventing routes and blocks their cart transfer', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $activity = plannerActivity($this->destination, ['latitude' => null, 'longitude' => null]);
    plannerAdd($this->plan, 'activity', $activity->id);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    expect($itinerary->snapshot['unscheduled'][0]['name'])->toBe($activity->activity_name);
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertUnprocessable();
    expect(CartItem::count())->toBe(0);
});

it('labels weather beyond the forecast horizon provisional and respects a fixed departure', function () {
    $this->partialMock(WeatherService::class, fn ($mock) => $mock->shouldReceive('forecastForDestination')->andReturn([]));
    plannerAdd($this->plan, 'room', $this->room->id);
    $activity = plannerActivity($this->destination, ['scheduling' => ['duration_minutes' => 60, 'fixed_start_times' => ['10:00'], 'opens_at' => '09:00', 'closes_at' => '18:00', 'environment' => 'outdoor']]);
    plannerAdd($this->plan, 'activity', $activity->id, ['date' => '2026-10-15']);
    $snapshot = app(ItineraryService::class)->generate($this->plan->fresh())->snapshot;
    expect($snapshot['days'][1]['stops'][0]['start'])->toBe('10:00')
        ->and($snapshot['days'][1]['stops'][0]['weather']['provisional'])->toBeTrue()
        ->and($snapshot['days'][1]['stops'][0]['weather']['rain_mm_per_hour'])->toBeNull();
});

it('rejects overlapping hotels and out of range stays', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id]);
    plannerAdd($this->plan, 'hotel', $hotel->id);
    $this->postJson(route('trip-planner.generate', $this->plan))->assertUnprocessable()->assertJsonValidationErrors('stays');
    $this->postJson(route('trip-planner.add', $this->plan), ['item_type' => 'room', 'item_id' => $this->room->id, 'check_in' => '2026-10-13', 'check_out' => '2026-10-17'])
        ->assertUnprocessable();
});

it('transfers each version once with its scheduled dates and keeps normal cart dedupe separate', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $activity = plannerActivity($this->destination);
    plannerAdd($this->plan, 'activity', $activity->id);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertOk()->assertJsonPath('data.status', 'added');
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertOk()->assertJsonPath('data.status', 'already_transferred');
    expect(CartItem::count())->toBe(2)->and(CartItem::where('item_type', 'activity')->first()->check_in_date->toDateString())->toBe('2026-10-15');
    $this->postJson(route('cart.add'), ['item_type' => 'activity', 'item_id' => $activity->id, 'quantity' => 1, 'selected_pax' => 1])->assertOk();
    expect(CartItem::count())->toBe(3)->and(CartItem::where('trip_itinerary_id', $itinerary->id)->where('item_type', 'activity')->first()->quantity)->toBe(2);
});

it('groups nearby visits and does not transfer a regenerated unchanged revision twice', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $first = plannerActivity($this->destination);
    $second = plannerActivity($this->destination);
    plannerAdd($this->plan, 'activity', $first->id);
    plannerAdd($this->plan, 'activity', $second->id);
    $revision = $this->plan->fresh()->revision;
    plannerAdd($this->plan, 'activity', $second->id);
    expect($this->plan->fresh()->revision)->toBe($revision);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    expect($itinerary->snapshot['days'][1]['stops'])->toHaveCount(2);
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertOk();
    $regenerated = app(ItineraryService::class)->generate($this->plan->fresh());
    $this->postJson(route('trip-planner.transfer', [$this->plan, $regenerated]))->assertOk()->assertJsonPath('data.status', 'already_transferred');
    expect(CartItem::count())->toBe(3);
});

it('invalidates transfer after edits and keeps earlier snapshots immutable', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    plannerAdd($this->plan, 'activity', plannerActivity($this->destination)->id);
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertUnprocessable();
    expect($itinerary->fresh()->snapshot['selections'])->toHaveCount(1);
});

it('rejects checkout when a transferred planner line has been changed in the cart', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertOk();
    CartItem::query()->update(['check_out_date' => '2026-10-18']);
    expect(fn () => app(BookingRequestService::class)->buildFromCart(Request::create('/checkout', 'POST'), [
        'contact_name' => 'Planner Guest', 'contact_email' => $this->user->email, 'contact_phone' => '09123456789',
    ]))->toThrow(ValidationException::class);
    expect(Booking::count())->toBe(0);
});

it('preserves cart provenance when the user removes a choice from a later draft', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    $originalId = $this->plan->items()->first()->id;
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertOk();
    $this->deleteJson(route('trip-planner.remove', [$this->plan, $originalId]))->assertOk();
    $booking = app(BookingRequestService::class)->buildFromCart(Request::create('/checkout', 'POST'), [
        'contact_name' => 'Planner Guest', 'contact_email' => $this->user->email, 'contact_phone' => '09123456789',
    ]);
    expect($booking->trip_itineraries[0]['booked_plan_item_ids'])->toBe([$originalId]);
});

it('includes the real road transfer between sequential hotel stays', function () {
    plannerAdd($this->plan, 'room', $this->room->id, ['check_in' => '2026-10-14', 'check_out' => '2026-10-16']);
    $hotel = HotelModel::factory()->create(['destination_id' => $this->destination->id]);
    plannerAdd($this->plan, 'hotel', $hotel->id, ['check_in' => '2026-10-16', 'check_out' => '2026-10-17']);
    $snapshot = app(ItineraryService::class)->generate($this->plan->fresh())->snapshot;
    expect($snapshot['days'][2]['legs'][0]['from'])->toBe($this->hotel->hotel_name)
        ->and($snapshot['days'][2]['legs'][0]['to'])->toBe($hotel->hotel_name)
        ->and($snapshot['days'][2]['distance_km'])->toBe(1.25);
});

it('offers protected print and PDF documents and queues itinerary email to the owner', function () {
    $this->room->update(['room_name' => 'Cove View Suite']);
    plannerAdd($this->plan, 'room', $this->room->id, ['quantity' => 2, 'pax' => 3]);
    plannerAdd($this->plan, 'activity', plannerActivity($this->destination)->id);
    plannerAdd($this->plan, 'activity', plannerActivity($this->destination)->id);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    expect($itinerary->snapshot['days'][0]['hotel']['rooms'][0])->toMatchArray(['name' => 'Cove View Suite', 'quantity' => 2, 'pax' => 3]);
    $this->room->update(['room_name' => 'Renamed After Generation']);
    $this->get(route('trip-planner.document', [$this->plan, $itinerary, 'print' => 1]))->assertOk()->assertSee('Strategic report')->assertSee('Print itinerary')->assertSee('Cove View Suite')->assertDontSee('Renamed After Generation');
    $document = $this->get(route('trip-planner.document', [$this->plan, $itinerary]))->assertOk()->assertHeader('content-type', 'application/pdf');
    if (($previewPath = getenv('PLANNER_PDF_PREVIEW_PATH')) !== false) {
        file_put_contents($previewPath, $document->getContent());
    }
    Notification::fake([TripItineraryReady::class]);
    $this->postJson(route('trip-planner.email', [$this->plan, $itinerary]))->assertOk();
    Notification::assertSentTo($this->user, TripItineraryReady::class);
});

it('lets admins verify attraction scheduling and refuses malformed times', function () {
    $admin = AdminModel::create(['name' => 'Planner Admin', 'email' => 'planner-admin@example.com', 'password' => 'password']);
    $attraction = TouristAttraction::factory()->create(['destination_id' => $this->destination->id]);
    $this->patchJson(route('admin.planner-inventory.update', ['attraction', $attraction]), ['duration_minutes' => 120])->assertUnauthorized();
    $this->actingAs($admin, 'admin');
    $this->get(route('admin.planner-inventory.index'))->assertOk()->assertSee('Planner inventory');
    $this->patch(route('admin.planner-inventory.update', ['attraction', $attraction]), [
        'latitude' => 11.96, 'longitude' => 121.927, 'duration_minutes' => 120, 'environment' => 'outdoor', 'opens_at' => '08:00', 'closes_at' => '17:00', 'is_shown' => true,
    ])->assertRedirect();
    expect($attraction->fresh()->scheduling['duration_minutes'])->toBe(120);
    $this->patchJson(route('admin.planner-inventory.update', ['attraction', $attraction]), ['latitude' => 11.96, 'longitude' => 121.927, 'fixed_start_times' => '25:30'])->assertUnprocessable();
});

it('preserves the itinerary and the purchased subset on the booking after checkout', function () {
    plannerAdd($this->plan, 'room', $this->room->id);
    plannerAdd($this->plan, 'activity', plannerActivity($this->destination)->id);
    $itinerary = app(ItineraryService::class)->generate($this->plan->fresh());
    $this->postJson(route('trip-planner.transfer', [$this->plan, $itinerary]))->assertOk();
    CartItem::where('item_type', 'activity')->update(['is_selected' => false]);
    $booking = app(BookingRequestService::class)->buildFromCart(Request::create('/checkout', 'POST'), [
        'contact_name' => 'Planner Guest', 'contact_email' => $this->user->email, 'contact_phone' => '09123456789',
    ]);
    expect($booking->trip_itineraries)->toHaveCount(1)->and($booking->trip_itineraries[0]['booked_plan_item_ids'])->toHaveCount(1)
        ->and($booking->trip_itineraries[0]['days'])->toHaveCount(4);
    $this->get(route('booking.show', $booking->booking_code))->assertOk()->assertSee('Your saved itinerary');
});
