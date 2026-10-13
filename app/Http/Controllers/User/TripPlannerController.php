<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\TripItinerary;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use App\Notifications\TripItineraryReady;
use App\Services\ItineraryService;
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
    public function index(Request $request): View
    {
        return view('trip-planner.index', [
            'destinations' => DestinationModel::query()->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']),
            'plans' => TripPlan::query()->where('user_id', $request->user()->id)->latest('updated_at')->limit(20)->get(),
            'initialDestination' => $request->integer('destination'),
            'emailPreferences' => ['reminders' => (bool) $request->user()->trip_reminders_enabled, 'marketing' => (bool) $request->user()->trip_marketing_enabled],
        ]);
    }

    public function catalog(DestinationModel $destination, TripPlannerService $planner): JsonResponse
    {
        return response()->json(['data' => $planner->catalog($destination)]);
    }

    public function weather(DestinationModel $destination, WeatherService $weather): View
    {
        return view('trip-planner._weather', [
            'destination' => $destination,
            'summary' => $weather->summaryForDestination($destination),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'destination_id' => ['required', 'integer', 'exists:destinations,id'],
            'title' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'pax' => ['required', 'integer', 'between:1,20'],
            'mode' => ['required', 'in:walking,driving'],
        ]);
        if (Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) > 29) {
            throw ValidationException::withMessages(['end_date' => 'Plan up to 30 calendar days at a time.']);
        }
        $plan = TripPlan::create($data + ['user_id' => $request->user()->id]);

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
        ]);
        $item = $planner->add($plan, $data);

        return response()->json(['data' => $item, 'items' => $plan->items()->get(), 'revision' => $plan->fresh()->revision]);
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
        DB::transaction(function () use ($plan, $item) {
            $locked = TripPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $item->delete();
            $locked->increment('revision');
        });

        return response()->json(['revision' => $plan->fresh()->revision]);
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
        $data = ['itinerary' => $itinerary->snapshot, 'reference' => 'Plan '.$plan->id.' · Version '.$itinerary->id];
        if ($request->boolean('print')) {
            return view('trip-planner.document', $data);
        }

        return Pdf::loadView('trip-planner.document', $data)->setPaper('a4')->download('sunnytrips-itinerary-'.$itinerary->id.'.pdf');
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
