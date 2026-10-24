<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\TripItinerary;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use App\Notifications\TripItineraryReady;
use App\Services\CartService;
use App\Services\Chat\ChatbotTripPlanService;
use App\Services\ItineraryService;
use App\Services\TripBookingDatePolicy;
use App\Services\TripDurationPolicy;
use App\Services\TripPlannerService;
use App\Services\WeatherService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TripPlannerController extends Controller
{
    public function __construct(private ChatbotTripPlanService $drafts) {}

    public function index(Request $request, TripBookingDatePolicy $dates, CartService $cart, ChatbotTripPlanService $drafts): View
    {
        $drafts->claimGuestPlans($request->user(), $cart->getSessionToken($request));

        return view('trip-planner.index', [
            'destinations' => DestinationModel::query()->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']),
            'plans' => TripPlan::query()->where('user_id', $request->user()->id)->latest('updated_at')->orderByDesc('id')->limit(20)->get(),
            'initialPlan' => $request->integer('plan'),
            'initialDestination' => $request->integer('destination'),
            'emailPreferences' => ['reminders' => (bool) $request->user()->trip_reminders_enabled, 'marketing' => (bool) $request->user()->trip_marketing_enabled],
            'datePolicy' => $dates->frontendConfig(),
        ]);
    }

    public function catalog(DestinationModel $destination, TripPlannerService $planner): JsonResponse
    {
        return response()->json(['data' => $planner->catalog($destination)]);
    }

    public function plans(Request $request, CartService $cart, ChatbotTripPlanService $drafts): JsonResponse
    {
        $drafts->claimGuestPlans($request->user(), $cart->getSessionToken($request));

        return response()->json(TripPlan::query()->where('user_id', $request->user()->id)->latest('updated_at')->orderByDesc('id')->simplePaginate(20));
    }

    public function update(Request $request, TripPlan $plan, TripBookingDatePolicy $dates, TripPlannerService $planner): JsonResponse
    {
        $this->owned($request, $plan);
        $data = $request->validate([
            'revision' => ['required', 'integer'], 'title' => ['sometimes', 'required', 'string', 'max:150'],
            'start_date' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:'.$dates->minimumArrivalDate()],
            'end_date' => ['required_with:start_date', 'date_format:Y-m-d', 'after:start_date'],
            'pax' => ['sometimes', 'required', 'integer', 'between:1,20'],
            'duration_days' => ['sometimes', 'required', 'integer', 'between:'.TripDurationPolicy::MIN_DAYS.','.TripDurationPolicy::MAX_DAYS],
            'mode' => ['sometimes', 'required', 'in:walking,driving'],
            'remove_choice' => ['sometimes', 'string', 'regex:/^(room|hotel|activity):[0-9]+$/'],
            'clear_dates' => ['sometimes', 'accepted', 'prohibits:start_date,end_date'],
        ], ['start_date.after_or_equal' => $dates->message()]);
        if (isset($data['start_date']) && Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) > 29) {
            throw ValidationException::withMessages(['end_date' => 'Plan up to 30 calendar days at a time.']);
        }
        // Any saved trip can be rescheduled; only a chatbot draft may go back to "dates undecided".
        if (isset($data['clear_dates']) && ! ($plan->source === 'chatbot' && $plan->status === 'draft')) {
            throw ValidationException::withMessages(['clear_dates' => 'A saved trip needs travel dates. Change them instead of clearing them.']);
        }
        DB::transaction(function () use ($plan, $data, $planner): void {
            $locked = TripPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($locked->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Your trip changed. Reload it before saving your edits.']);
            }
            $previous = ['start_date' => $locked->start_date?->toDateString(), 'end_date' => $locked->end_date?->toDateString(), 'pax' => $locked->pax];
            $locked->fill(array_intersect_key($data, array_flip(['title', 'start_date', 'end_date', 'pax', 'mode'])));
            if (isset($data['clear_dates'])) {
                $locked->forceFill(['start_date' => null, 'end_date' => null]);
                // Undated choices keep only what does not depend on a date.
                foreach ($locked->items()->get() as $item) {
                    $item->update(['options' => array_intersect_key($item->options ?? [], array_flip(['pax', 'quantity', 'day']))]);
                }
            }
            if (isset($data['pax'])) {
                $locked->context = array_replace($locked->context ?? [], ['pax_assumed' => false]);
            }
            // The trip length is authoritative: dates must cover it exactly, and a
            // length change on a dated trip keeps the arrival and moves the departure.
            if (isset($data['start_date'])) {
                $selected = $data['duration_days'] ?? $locked->duration_days;
                if ($selected) {
                    TripDurationPolicy::assertDays((int) $selected, true, 'duration_days');
                    TripDurationPolicy::assertMatches($data['start_date'], $data['end_date'], (int) $selected, 'end_date');
                }
                $locked->duration_days = TripDurationPolicy::days($data['start_date'], $data['end_date']);
            } elseif (isset($data['duration_days'])) {
                if ($locked->start_date !== null) {
                    TripDurationPolicy::assertDays((int) $data['duration_days'], true, 'duration_days');
                    $locked->end_date = TripDurationPolicy::endDate($locked->start_date->toDateString(), (int) $data['duration_days']);
                }
                $locked->duration_days = (int) $data['duration_days'];
            }
            $locked->revision++;
            $locked->save();
            $tripChanged = $locked->start_date?->toDateString() !== $previous['start_date']
                || $locked->end_date?->toDateString() !== $previous['end_date'] || $locked->pax !== $previous['pax'];
            if ($tripChanged && $locked->start_date !== null) {
                foreach ($locked->items()->get() as $item) {
                    $planner->add($locked->fresh(), $this->rebuiltChoice($item, $locked, $previous));
                }
            }
            if (isset($data['remove_choice'])) {
                [$type, $id] = explode(':', $data['remove_choice']);
                $locked->items()->where('item_type', $type)->where('item_id', $id)->delete();
            }
            $this->recordDraft($locked->fresh(), $data['remove_choice'] ?? null);
        });

        return $this->show($request, $plan->fresh());
    }

    /**
     * The add() payload that re-checks a choice after the trip dates or party
     * size change. Values that still matched the previous trip follow the new
     * one; custom stay dates, pinned visit dates and traveler counts are kept,
     * with dates falling back to the trip's when they no longer fit inside it.
     *
     * @param  array{start_date: ?string, end_date: ?string, pax: int}  $previous
     * @return array{item_type: string, item_id: int, quantity: int, pax: int, check_in?: string, check_out?: string, date?: string}
     */
    private function rebuiltChoice(TripPlanItem $item, TripPlan $plan, array $previous): array
    {
        $options = $item->options ?? [];
        $inTrip = fn (?string $date): bool => $date !== null && $date >= $plan->start_date->toDateString() && $date <= $plan->end_date->toDateString();
        $pax = $options['pax'] ?? $previous['pax'];
        $choice = ['item_type' => $item->item_type, 'item_id' => $item->item_id, 'quantity' => $options['quantity'] ?? 1,
            'pax' => $pax === $previous['pax'] ? $plan->pax : $pax];
        $customStay = ($options['check_in'] ?? null) !== $previous['start_date'] || ($options['check_out'] ?? null) !== $previous['end_date'];
        if ($customStay && $inTrip($options['check_in'] ?? null) && $inTrip($options['check_out'] ?? null)) {
            $choice += ['check_in' => $options['check_in'], 'check_out' => $options['check_out']];
        }
        if ($inTrip($options['date'] ?? null)) {
            $choice['date'] = $options['date'];
        }

        return $choice;
    }

    private function recordDraft(TripPlan $plan, ?string $removed = null, ?array $added = null): void
    {
        $snapshot = $plan->itineraries()->latest('id')->first()?->snapshot;
        if (($snapshot['kind'] ?? null) !== 'chatbot_draft') {
            return;
        }
        $ids = $plan->items()->pluck('id')->all();
        $snapshot['choices'] = array_values(array_filter($snapshot['choices'], fn (array $choice): bool => ($choice['type'].':'.$choice['id']) !== $removed
            && (empty($choice['plan_item_id']) || in_array($choice['plan_item_id'], $ids, true))));
        if ($added !== null) {
            $snapshot['choices'] = array_values(array_filter($snapshot['choices'], fn (array $choice): bool => $choice['type'] !== $added['type'] || $choice['id'] !== $added['id']));
            $snapshot['choices'][] = $added;
        }
        foreach ($snapshot['choices'] as &$choice) {
            if ($choice['day'] > $plan->duration_days) {
                $choice['day'] = null;
            }
        }
        unset($choice);
        $snapshot['title'] = $plan->title;
        $snapshot['start_date'] = $plan->start_date?->toDateString();
        $snapshot['end_date'] = $plan->end_date?->toDateString();
        $snapshot['pax'] = $plan->pax;
        $snapshot['duration_days'] = $plan->duration_days;
        $snapshot['estimate'] = null;
        $snapshot['duration_nights'] = max(0, $plan->duration_days - 1);
        $snapshot['accommodation'] = $this->drafts->refreshAccommodation($plan, $snapshot, ($added['type'] ?? null) === 'room' ? $added['id'] : null);
        if ($snapshot['accommodation'] !== null) {
            $snapshot['accommodation_notice'] = null;
        }
        $snapshot['days'] = array_map(fn (int $day): array => ['day' => $day, 'date' => $plan->start_date?->copy()->addDays($day - 1)->toDateString(), 'choices' => array_values(array_filter($snapshot['choices'], fn (array $choice): bool => $choice['day'] === $day))], range(1, $plan->duration_days));
        $plan->itineraries()->create(['revision' => $plan->revision, 'snapshot' => $snapshot]);
    }

    public function weather(DestinationModel $destination, WeatherService $weather): View
    {
        return view('trip-planner._weather', [
            'destination' => $destination,
            'summary' => $weather->summaryForDestination($destination),
        ]);
    }

    public function store(Request $request, TripBookingDatePolicy $dates): JsonResponse
    {
        $data = $request->validate([
            'destination_id' => ['required', 'integer', 'exists:destinations,id'],
            'title' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$dates->minimumArrivalDate()],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'duration_days' => ['sometimes', 'integer', 'between:'.TripDurationPolicy::MIN_DATED_DAYS.','.TripDurationPolicy::MAX_DAYS],
            'pax' => ['required', 'integer', 'between:1,20'],
            'mode' => ['required', 'in:walking,driving'],
        ], ['start_date.after_or_equal' => $dates->message()]);
        if (isset($data['duration_days'])) {
            TripDurationPolicy::assertMatches($data['start_date'], $data['end_date'], (int) $data['duration_days'], 'end_date');
        }
        if (Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) > 29) {
            throw ValidationException::withMessages(['end_date' => 'Plan up to 30 calendar days at a time.']);
        }
        $plan = TripPlan::create(['duration_days' => TripDurationPolicy::days($data['start_date'], $data['end_date'])] + $data + ['user_id' => $request->user()->id]);

        return response()->json(['data' => $plan], 201);
    }

    public function show(Request $request, TripPlan $plan): JsonResponse
    {
        $this->owned($request, $plan);

        return response()->json(['data' => $plan->load('items'), 'itinerary' => $plan->itineraries()->latest('id')->first()]);
    }

    public function add(Request $request, TripPlan $plan, TripPlannerService $planner): JsonResponse
    {
        $this->owned($request, $plan);
        $data = $request->validate([
            'item_type' => ['required', 'in:hotel,room,activity'], 'item_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'between:1,20'], 'pax' => ['sometimes', 'integer', 'between:1,20'],
            'check_in' => ['nullable', 'date_format:Y-m-d'], 'check_out' => ['nullable', 'date_format:Y-m-d'], 'date' => ['nullable', 'date_format:Y-m-d'],
            'revision' => ['sometimes', 'integer'],
        ]);
        $item = DB::transaction(function () use ($plan, $data, $planner): TripPlanItem {
            $item = $planner->add($plan, $data);
            if ($plan->status === 'draft') {
                $model = $planner->inventory($item->item_type, $item->item_id, $plan->destination_id);
                $listing = $planner->listing($item->item_type, $model);
                $this->recordDraft($plan->fresh(), added: ['type' => $item->item_type, 'id' => $item->item_id, 'name' => $listing['name'], 'day' => $item->options['day'] ?? null, 'plan_item_id' => $item->id, 'available' => true]);
            }

            return $item;
        });

        return response()->json(['data' => $item, 'items' => $plan->items()->get(), 'revision' => $plan->fresh()->revision,
            'draft_itinerary' => $plan->status === 'draft' ? $plan->itineraries()->latest('id')->first() : null]);
    }

    public function destroy(Request $request, TripPlan $plan): JsonResponse
    {
        $this->owned($request, $plan);
        DB::transaction(function () use ($plan) {
            $locked = TripPlan::query()->lockForUpdate()->findOrFail($plan->id);
            CartItem::query()->where('user_id', $locked->user_id)
                ->whereIn('trip_itinerary_id', $locked->itineraries()->select('id'))->delete();
            $locked->delete();
        });

        return response()->json(['message' => 'Saved trip deleted. Existing booking itineraries are preserved.']);
    }

    public function remove(Request $request, TripPlan $plan, TripPlanItem $item): JsonResponse
    {
        $this->owned($request, $plan);
        abort_unless($item->trip_plan_id === $plan->id, 404);
        $data = $request->validate(['revision' => ['sometimes', 'integer']]);
        DB::transaction(function () use ($plan, $item, $data) {
            $locked = TripPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if (isset($data['revision']) && $locked->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['plan' => 'The trip changed in another tab. Reload it before removing choices.']);
            }
            $item->delete();
            $locked->increment('revision');
            $this->recordDraft($locked);
        });

        return response()->json(['revision' => $plan->fresh()->revision, 'draft_itinerary' => $plan->status === 'draft' ? $plan->itineraries()->latest('id')->first() : null]);
    }

    public function generate(Request $request, TripPlan $plan, ItineraryService $engine): JsonResponse
    {
        $this->owned($request, $plan);

        return response()->json(['data' => $engine->generate($plan)]);
    }

    public function transfer(Request $request, TripPlan $plan, TripItinerary $itinerary, TripPlannerService $planner): JsonResponse
    {
        $this->owned($request, $plan);
        abort_unless($itinerary->trip_plan_id === $plan->id, 404);

        return response()->json(['data' => $planner->transfer($request, $plan, $itinerary), 'cart_url' => route('cart.index')]);
    }

    public function document(Request $request, TripPlan $plan, TripItinerary $itinerary): mixed
    {
        $this->owned($request, $plan);
        abort_unless($itinerary->trip_plan_id === $plan->id, 404);
        $data = ['itinerary' => $itinerary->snapshot, 'reference' => 'Plan '.$plan->id.' · Version '.$itinerary->id, 'referenceLabel' => 'Plan reference (not a booking)', 'referenceCode' => 'PLAN-'.$plan->id.'-V'.$itinerary->id];
        $view = ($itinerary->snapshot['kind'] ?? null) === 'chatbot_draft' ? 'trip-planner.draft-document' : 'trip-planner.document';
        if ($request->boolean('print')) {
            return view($view, $data);
        }

        return Pdf::loadView($view, $data)->setPaper('a4')->download('sunnytrips-itinerary-'.$itinerary->id.'.pdf');
    }

    private function owned(Request $request, TripPlan $plan): void
    {
        abort_unless($plan->user_id === $request->user()->id, 404);
    }

    public function email(Request $request, TripPlan $plan, TripItinerary $itinerary): JsonResponse
    {
        $this->owned($request, $plan);
        abort_unless($itinerary->trip_plan_id === $plan->id, 404);
        $request->user()->notify(new TripItineraryReady($itinerary));

        return response()->json(['message' => 'The itinerary will be emailed to your account address.']);
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate(['reminders' => ['required', 'boolean'], 'marketing' => ['required', 'boolean']]);
        $user = $request->user();
        $user->trip_reminders_enabled = $data['reminders'];
        $user->trip_marketing_enabled = $data['marketing'];
        $user->save();

        return response()->json(['message' => 'Email preferences saved. Booking and payment confirmations remain available.']);
    }
}
