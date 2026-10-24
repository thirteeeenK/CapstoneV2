<?php

namespace App\Services;

use App\Concerns\ResolvesImages;
use App\Models\ActivityModel;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\TripItinerary;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripPlannerService
{
    use ResolvesImages;

    public function __construct(private RoomAvailabilityService $availability, private CartService $cart, private TripBookingDatePolicy $dates) {}

    public function catalog(DestinationModel $destination): array
    {
        $hotels = HotelModel::query()->where('destination_id', $destination->id)->where('is_shown', true)
            ->with(['rooms' => fn ($q) => $q->where('is_shown', true)])->orderBy('hotel_name')->get();
        $activities = ActivityModel::query()->where('destination_id', $destination->id)->where('is_shown', true)->with('destination')->orderBy('activity_name')->get();

        return [
            'hotels' => $hotels->map(function (HotelModel $hotel): array {
                $hotel->rooms->each(fn (RoomType $room) => $room->setRelation('hotel', $hotel));

                return $this->listing('hotel', $hotel) + ['rooms' => $hotel->rooms->map(fn (RoomType $room) => $this->listing('room', $room))->values()->all()];
            })->all(),
            'activities' => $activities->map(fn (ActivityModel $activity) => $this->listing('activity', $activity))->all(),
        ];
    }

    public function inventory(string $type, int $id, int $destinationId): Model
    {
        $model = $this->inventoryQuery($type, $destinationId)->find($id);
        if (! $model) {
            throw ValidationException::withMessages(['item_id' => 'This choice is no longer listed for this destination.']);
        }

        return $model;
    }

    public function selectedInventory(TripPlan $plan): array
    {
        $models = [];
        foreach ($plan->items->groupBy('item_type') as $type => $items) {
            $selected = $this->inventoryQuery($type, $plan->destination_id)->whereIn('id', $items->pluck('item_id'))->get()->keyBy('id');
            if ($selected->count() !== $items->count()) {
                throw ValidationException::withMessages(['items' => 'A selected listing is no longer available. Remove it or choose another listing before generating.']);
            }
            $models[$type] = $selected;
        }

        return $models;
    }

    private function inventoryQuery(string $type, int $destinationId): Builder
    {
        $query = match ($type) {
            'hotel' => HotelModel::query()->where('destination_id', $destinationId),
            'room' => RoomType::query()->with('hotel')->whereHas('hotel', fn ($q) => $q->where('destination_id', $destinationId)->where('is_shown', true)),
            'activity' => ActivityModel::query()->where('destination_id', $destinationId)->with('destination'),
            default => throw ValidationException::withMessages(['item_type' => 'Choose a supported planner item.']),
        };

        return $query->where('is_shown', true);
    }

    public function listing(string $type, Model $model): array
    {
        $location = $type === 'room' ? $model->hotel : $model;
        [$latitude, $longitude] = $type === 'activity'
            ? [$model->latitude_with_fallback, $model->longitude_with_fallback]
            : [$location->latitude, $location->longitude];

        return [
            'type' => $type, 'id' => $model->id,
            'name' => match ($type) {
                'hotel' => $model->hotel_name, 'room' => $model->room_name, 'activity' => $model->activity_name, default => $model->name
            },
            'description' => (string) ($model->description ?? $model->hotel_description ?? ''),
            'lat' => $latitude === null ? null : (float) $latitude,
            'lng' => $longitude === null ? null : (float) $longitude,
            // 'approximate' is an activity pinned to its destination centre, so its travel times are estimates.
            'location_precision' => $latitude === null || $longitude === null ? 'missing'
                : ($type === 'activity' && ! $model->hasExactLocation() ? 'approximate' : 'exact'),
            'image' => $type === 'activity'
                ? self::resolveActivityImage($model->images[0] ?? null, $model->activity_name, $model->category)
                : self::resolveImg($model->images[0] ?? null),
            'image_is_fallback' => empty($model->images[0]),
            'price' => $type === 'room' ? (float) $model->base_price : ($type === 'activity' ? $model->rate : null),
            'hotel_id' => $type === 'room' ? $model->hotel_id : ($type === 'hotel' ? $model->id : null),
            'hotel_name' => $type === 'room' ? $model->hotel->hotel_name : null,
            'max_pax' => $type === 'room' ? $model->max_occupancy : ($type === 'activity' ? $model->getMaxCapacityInt() : null),
            'base_price' => $type === 'room' ? (float) $model->base_price : null,
            'base_occupancy' => $type === 'room' ? (int) ($model->base_occupancy ?: 2) : null,
            'extra_person_fee' => $type === 'room' ? (float) ($model->extra_person_fee ?: 0) : null,
            'rate_min' => $type === 'activity' ? (float) $model->calculateRateForPax(1) : null,
            'rate_max' => $type === 'activity' ? (float) $model->calculateRateForPax(2) : null,
            'per_person' => $type === 'activity' ? $model->isPerPersonRate() : null,
            'scheduling' => (array) ($model->scheduling ?? []),
            'duration' => $type === 'activity' ? $model->duration : null,
        ];
    }

    public function add(TripPlan $plan, array $data): TripPlanItem
    {
        $model = $this->inventory($data['item_type'], $data['item_id'], $plan->destination_id);
        $options = $this->options($plan, $data, $model);

        return DB::transaction(function () use ($plan, $data, $options, $model) {
            $locked = TripPlan::query()->lockForUpdate()->findOrFail($plan->id);
            // The bound plan only catches changes during this request; the browser's revision catches a stale tab.
            if ($locked->revision !== $plan->revision || (isset($data['revision']) && $locked->revision !== (int) $data['revision'])) {
                throw ValidationException::withMessages(['plan' => 'The trip changed in another tab. Reload it before adding choices.']);
            }
            if ($locked->items()->count() >= 100 && ! $locked->items()->where('item_type', $data['item_type'])->where('item_id', $data['item_id'])->exists()) {
                throw ValidationException::withMessages(['items' => 'This trip supports up to 100 choices.']);
            }
            $existing = $locked->items()->where('item_type', $data['item_type'])->where('item_id', $data['item_id'])->first();
            if ($existing && $existing->options == $options) {
                return $existing;
            }
            $item = $locked->items()->updateOrCreate(['item_type' => $data['item_type'], 'item_id' => $data['item_id']], ['options' => $options]);
            if ($data['item_type'] === 'hotel') {
                $rooms = $locked->items()->where('item_type', 'room')->get();
                $inventory = RoomType::query()->where('hotel_id', $model->id)->whereIn('id', $rooms->pluck('item_id'))->get()->keyBy('id');
                foreach ($rooms as $room) {
                    if ($inventory->has($room->item_id)) {
                        $roomData = ['item_type' => 'room'] + $room->options;
                        $roomData['check_in'] = $options['check_in'];
                        $roomData['check_out'] = $options['check_out'];
                        $roomOptions = $this->options($locked, $roomData, $inventory[$room->item_id]);
                        $room->update(['options' => $roomOptions]);
                    }
                }
            }
            $locked->increment('revision');

            return $item;
        });
    }

    /**
     * Give every hotel and room choice the stay dates the scheduler reads.
     * Chatbot drafts save stays without them, so they are filled from the
     * hotel's stay or the trip dates, keeping the saved room count and guests,
     * and checked for availability and capacity like any other choice. Filling
     * derived defaults is not a user edit, so the plan revision is unchanged.
     */
    public function prepareForSchedule(TripPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $locked = TripPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $stays = $locked->items()->whereIn('item_type', ['hotel', 'room'])->get()
                ->reject(fn (TripPlanItem $item): bool => isset($item->options['check_in'], $item->options['check_out']))
                ->sortBy(fn (TripPlanItem $item): int => $item->item_type === 'hotel' ? 0 : 1);
            foreach ($stays as $item) {
                $model = $this->inventory($item->item_type, $item->item_id, $locked->destination_id);
                $item->update(['options' => $this->options($locked, ['item_type' => $item->item_type] + ($item->options ?? []), $model)]);
            }
        });
    }

    private function options(TripPlan $plan, array $data, Model $model): array
    {
        $type = $data['item_type'];
        $quantity = (int) ($data['quantity'] ?? 1);
        $pax = (int) ($data['pax'] ?? ($type === 'activity' ? 1 : $plan->pax));
        $options = ['quantity' => $quantity, 'pax' => $pax];
        if ($plan->start_date === null || $plan->end_date === null) {
            throw ValidationException::withMessages(['dates' => 'Set travel dates before changing dated selections. You can remove draft choices without dates.']);
        }
        if (in_array($type, ['hotel', 'room'], true)) {
            $hotelStay = $type === 'room' ? $plan->items()->where('item_type', 'hotel')->where('item_id', $model->hotel_id)->first()?->options : null;
            $in = Carbon::parse($data['check_in'] ?? $hotelStay['check_in'] ?? $plan->start_date->toDateString())->startOfDay();
            $out = Carbon::parse($data['check_out'] ?? $hotelStay['check_out'] ?? $plan->end_date->toDateString())->startOfDay();
            if ($in->lt($plan->start_date) || $out->gt($plan->end_date) || $out->lte($in)) {
                throw ValidationException::withMessages(['check_out' => 'Stay dates must fall within the trip, with check-out after check-in.']);
            }
            $options += ['check_in' => $in->toDateString(), 'check_out' => $out->toDateString()];
            if ($type === 'room') {
                if ($pax > $model->max_occupancy || $this->availability->check($model, $in, $out)['remaining'] < $quantity) {
                    throw ValidationException::withMessages(['room' => 'Not enough rooms or guest capacity for this selection.']);
                }
            }
        } elseif ($type === 'activity' && $pax > $model->getMaxCapacityInt()) {
            throw ValidationException::withMessages(['pax' => 'The number of guests exceeds this activity’s capacity.']);
        }
        if (! empty($data['date'])) {
            $date = Carbon::parse($data['date'])->startOfDay();
            if ($date->lt($plan->start_date) || $date->gt($plan->end_date)) {
                throw ValidationException::withMessages(['date' => 'Choose an activity date within this trip.']);
            }
            $options['date'] = $date->toDateString();
        }

        return $options;
    }

    public function transfer(Request $request, TripPlan $plan, TripItinerary $itinerary): array
    {
        return DB::transaction(function () use ($request, $plan, $itinerary) {
            $lockedPlan = TripPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked = TripItinerary::query()->lockForUpdate()->findOrFail($itinerary->id);
            if ($locked->trip_plan_id !== $plan->id || $locked->revision !== $lockedPlan->revision) {
                throw ValidationException::withMessages(['itinerary' => 'Generate a new itinerary after changing your selections.']);
            }
            $basketItineraryIds = CartItem::query()->where('user_id', $request->user()->id)
                ->whereIn('trip_itinerary_id', $plan->itineraries()->select('id'))->pluck('trip_itinerary_id')->unique();
            if ($plan->itineraries()->whereIn('id', $basketItineraryIds)->where('revision', $locked->revision)->exists()) {
                return ['status' => 'already_transferred'];
            }
            if ($basketItineraryIds->isNotEmpty()) {
                throw ValidationException::withMessages(['cart' => 'An earlier version is still in your Trip Basket. Remove it before transferring this version.']);
            }
            $snapshot = $locked->snapshot;
            if (($snapshot['kind'] ?? null) === 'chatbot_draft' || $lockedPlan->start_date === null || $lockedPlan->end_date === null) {
                throw ValidationException::withMessages(['itinerary' => 'Choose dates and build a checked schedule before adding this draft to your Trip Basket.']);
            }
            $this->dates->assertBookableArrival($lockedPlan->start_date->toDateString());
            $items = $lockedPlan->items()->get();
            $bookable = $items->whereIn('item_type', ['room', 'activity']);
            if ($bookable->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Select at least one room or bookable activity. A hotel base alone is a planning choice.']);
            }
            $scheduledIds = collect($snapshot['days'])->flatMap(fn ($day) => $day['stops'])->pluck('plan_item_id')->all();
            $transferred = 0;
            foreach ($bookable->sortBy('item_id') as $item) {
                if ($item->item_type === 'activity' && ! in_array($item->id, $scheduledIds, true)) {
                    continue;
                }
                $model = $this->inventory($item->item_type, $item->item_id, $plan->destination_id);
                $options = $item->options;
                if ($item->item_type === 'room') {
                    $model = RoomType::query()->lockForUpdate()->findOrFail($model->id);
                    if ($this->availability->check($model, Carbon::parse($options['check_in']), Carbon::parse($options['check_out']))['remaining'] < $options['quantity']) {
                        throw ValidationException::withMessages(['room' => 'A selected room is no longer available. Review your choices.']);
                    }
                }
                $stop = collect($snapshot['days'])->flatMap(fn ($day) => $day['stops'])->firstWhere('plan_item_id', $item->id);
                CartItem::create([
                    'user_id' => $request->user()->id, 'session_token' => $this->cart->getSessionToken($request),
                    'item_type' => $item->item_type, 'item_id' => $item->item_id,
                    'quantity' => $item->item_type === 'room' ? $options['quantity'] : $options['pax'], 'selected_pax' => $options['pax'],
                    'check_in_date' => $item->item_type === 'room' ? $options['check_in'] : ($stop['date'] ?? null),
                    'check_out_date' => $item->item_type === 'room' ? $options['check_out'] : null,
                    'is_selected' => true, 'trip_itinerary_id' => $locked->id, 'trip_plan_item_id' => $item->id,
                    'notes' => 'Trip plan: '.$plan->title,
                ]);
                $transferred++;
            }
            if ($transferred === 0) {
                throw ValidationException::withMessages(['items' => 'None of your bookable choices could be scheduled on the map yet. Switch to walking, change the dates, or adjust your choices, then generate again.']);
            }
            $locked->update(['transferred_at' => now()]);

            return ['status' => 'added', 'count' => $transferred];
        });
    }
}
