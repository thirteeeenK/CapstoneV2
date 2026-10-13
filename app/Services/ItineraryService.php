<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\TripItinerary;
use App\Models\TripPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItineraryService
{
    public function __construct(private RoutingService $routing, private WeatherService $weather, private TripPlannerService $planner) {}

    public function generate(TripPlan $plan): TripItinerary
    {
        $plan->load(['items', 'destination']);
        if ($plan->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add a hotel or room and the places you want to visit first.']);
        }
        $revision = $plan->revision;
        $choices = [];
        $stays = [];
        $points = [];
        $models = $this->planner->selectedInventory($plan);
        foreach ($plan->items as $item) {
            $model = $models[$item->item_type][$item->item_id];
            $choice = $this->planner->listing($item->item_type, $model) + ['plan_item_id' => $item->id, 'options' => $item->options];
            $choice['point'] = ['lat' => $choice['lat'], 'lng' => $choice['lng']];
            $choice['point_index'] = count($points);
            $points[] = $choice['point'];
            if (in_array($item->item_type, ['hotel', 'room'], true)) {
                $hotel = $item->item_type === 'room' ? $model->hotel : $model;
                $choice['room_name'] = $item->item_type === 'room' ? $choice['name'] : null;
                $choice['name'] = $hotel->hotel_name;
                $choice['check_in_time'] = substr($hotel->check_in_time ?? '15:00', 0, 5);
                $choice['check_out_time'] = substr($hotel->check_out_time ?? '11:00', 0, 5);
                $choice['times_assumed'] = ! $hotel->check_in_time || ! $hotel->check_out_time;
                $stays[] = $choice;
            } else {
                $choice['scheduling'] = $this->scheduling($model, $choice);
                $choices[$item->id] = $choice;
            }
        }
        if ($stays === []) {
            throw ValidationException::withMessages(['hotel' => 'Choose a hotel or room as your route starting point. A hotel choice alone is not a reservation.']);
        }
        foreach ($stays as $a) {
            foreach ($stays as $b) {
                if ($a['hotel_id'] !== $b['hotel_id'] && $a['options']['check_in'] < $b['options']['check_out'] && $b['options']['check_in'] < $a['options']['check_out']) {
                    throw ValidationException::withMessages(['stays' => 'Two different hotels overlap. Assign separate stay dates to each hotel.']);
                }
            }
        }
        $matrix = $this->routing->matrix($points, $points, $plan->mode);
        $rawWeather = $this->weather->forecastForDestination($plan->destination) ?? [];
        $rawWeather = ['slots' => $this->weather->forecastSlots($rawWeather), 'stale' => (bool) ($rawWeather['_stale'] ?? false), 'fetched_at' => $rawWeather['_fetched_at'] ?? null];
        $days = [];
        $unscheduled = [];
        for ($date = $plan->start_date->copy(); $date->lte($plan->end_date); $date->addDay()) {
            $key = $date->toDateString();
            $stay = collect($stays)->first(fn ($stay) => $stay['options']['check_in'] <= $key && $stay['options']['check_out'] > $key)
                ?? collect($stays)->first(fn ($stay) => $stay['options']['check_out'] === $key);
            if ($stay) {
                $stay['rooms'] = collect($stays)
                    ->filter(fn ($selected) => $selected['type'] === 'room' && $selected['hotel_id'] === $stay['hotel_id']
                        && $selected['options']['check_in'] <= $key && $selected['options']['check_out'] >= $key)
                    ->map(fn ($selected) => [
                        'plan_item_id' => $selected['plan_item_id'], 'name' => $selected['room_name'],
                        'quantity' => $selected['options']['quantity'], 'pax' => $selected['options']['pax'],
                        'check_in' => $selected['options']['check_in'], 'check_out' => $selected['options']['check_out'],
                    ])->values()->all();
            }
            $start = $date->copy()->setTimezone('Asia/Manila')->setTime(9, 0);
            $end = $date->copy()->setTimezone('Asia/Manila')->setTime(18, 0);
            $events = [];
            $legs = [];
            if ($stay && $stay['options']['check_in'] === $key) {
                $start = Carbon::parse($key.' '.$stay['check_in_time'], 'Asia/Manila')->addMinutes(30);
                $events[] = ['name' => 'Check in at '.$stay['name'], 'time' => $stay['check_in_time'], 'assumed' => $stay['times_assumed']];
                $departing = collect($stays)->first(fn ($other) => $other['hotel_id'] !== $stay['hotel_id'] && $other['options']['check_out'] === $key);
                if ($departing) {
                    $travel = $matrix['durations'][$departing['point_index']][$stay['point_index']] ?? null;
                    if ($travel === null) {
                        $unscheduled[] = ['plan_item_id' => $stay['plan_item_id'], 'type' => $stay['type'], 'name' => $stay['name'], 'reason' => 'The hotel change has no verified land route. Arrange the transfer before scheduling this day.'];
                        $stay = null;
                    } else {
                        $transfer = $this->leg($departing, $stay, $matrix, $plan->mode);
                        if ($transfer['geometry_status'] === 'unsupported_transfer') {
                            $unscheduled[] = ['plan_item_id' => $stay['plan_item_id'], 'type' => $stay['type'], 'name' => $stay['name'], 'reason' => 'The hotel change requires ferry travel, which needs a separate operator timetable and transfer booking.'];
                            $stay = null;
                            $days[] = ['day' => count($days) + 1, 'date' => $key, 'label' => $date->format('l, F j'), 'hotel' => null, 'cursor' => $start, 'end' => $end, 'previous' => null, 'events' => [], 'stops' => [], 'legs' => [], 'distance_km' => 0, 'travel_minutes' => 0];

                            continue;
                        }
                        $legs[] = $transfer;
                        $events[] = ['name' => 'Check out of '.$departing['name'].' and transfer to '.$stay['name'].' ('.$legs[0]['duration_minutes'].' min mapped travel)', 'time' => $departing['check_out_time'], 'assumed' => $departing['times_assumed']];
                        $arrival = Carbon::parse($key.' '.$departing['check_out_time'], 'Asia/Manila')->addSeconds($travel)->addMinutes(30);
                        $start = $start->max($arrival);
                    }
                }
            }
            if ($stay && $stay['options']['check_out'] === $key) {
                $end = Carbon::parse($key.' '.$stay['check_out_time'], 'Asia/Manila');
                $events[] = ['name' => 'Check out of '.$stay['name'], 'time' => $stay['check_out_time'], 'assumed' => $stay['times_assumed']];
            }
            $days[] = ['day' => count($days) + 1, 'date' => $key, 'label' => $date->format('l, F j'), 'hotel' => $stay,
                'cursor' => $start, 'end' => $end, 'previous' => $stay, 'events' => $events, 'stops' => [], 'legs' => $legs,
                'distance_km' => array_sum(array_column($legs, 'distance_km')), 'travel_minutes' => array_sum(array_column($legs, 'duration_minutes'))];
        }

        $initialAlternatives = [];
        while ($choices !== []) {
            $best = null;
            foreach ($choices as $choice) {
                foreach ($days as $index => $day) {
                    $candidate = $this->candidate($choice, $day, $matrix, $rawWeather);
                    if ($candidate) {
                        $initialAlternatives[$choice['plan_item_id']][$day['date']] ??= ['date' => $day['date'], 'weather' => array_intersect_key($candidate['weather'], array_flip(['known', 'risk', 'rain_probability', 'rain_mm_per_hour', 'wind_m_per_second']))];
                    }
                    if ($candidate && ($best === null || $candidate['score'] < $best['score'])) {
                        $best = $candidate + ['day_index' => $index, 'choice' => $choice];
                    }
                }
            }
            if ($best === null) {
                foreach ($choices as $choice) {
                    $unscheduled[] = ['plan_item_id' => $choice['plan_item_id'], 'type' => $choice['type'], 'name' => $choice['name'],
                        'reason' => ! $this->routing->validPoint($choice['point']) ? 'The precise meeting point or entrance is missing.' : (in_array($matrix['status'] ?? '', ['not_configured', 'unavailable'], true) ? 'Road routing is currently unavailable. Your choices are saved; generate again when routing is available.' : 'No feasible slot was found with a reachable land route, return travel, selected date, opening hours, duration and acceptable forecast.')];
                }
                break;
            }
            $day = &$days[$best['day_index']];
            $choice = $best['choice'];
            $leg = $this->leg($day['previous'], $choice, $matrix, $plan->mode);
            $returnLeg = $this->leg($choice, $day['hotel'], $matrix, $plan->mode);
            if ($leg['geometry_status'] === 'unsupported_transfer' || $returnLeg['geometry_status'] === 'unsupported_transfer') {
                if ($leg['geometry_status'] === 'unsupported_transfer') {
                    $matrix['durations'][$day['previous']['point_index']][$choice['point_index']] = null;
                }
                if ($returnLeg['geometry_status'] === 'unsupported_transfer') {
                    $matrix['durations'][$choice['point_index']][$day['hotel']['point_index']] = null;
                }
                unset($day);

                continue;
            }
            $best['alternatives'] = array_values($initialAlternatives[$choice['plan_item_id']] ?? []);
            $reason = $this->reason($choice, $day['previous'], $best, $leg, $plan->mode);
            $day['stops'][] = [
                'plan_item_id' => $choice['plan_item_id'], 'item_id' => $choice['id'], 'type' => $choice['type'], 'name' => $choice['name'], 'date' => $day['date'],
                'start' => $best['start']->format('H:i'), 'end' => $best['finish']->format('H:i'), 'duration_minutes' => $choice['scheduling']['duration_minutes'],
                'lat' => $choice['lat'], 'lng' => $choice['lng'], 'image' => $choice['image'], 'travel' => $leg, 'weather' => $best['weather'], 'reason' => $reason,
                'assumptions' => $choice['scheduling']['assumptions'],
            ];
            $day['legs'][] = $leg;
            $day['distance_km'] += $leg['distance_km'];
            $day['travel_minutes'] += $leg['duration_minutes'];
            $day['cursor'] = $best['finish']->copy()->addMinutes(15);
            $day['previous'] = $choice;
            unset($choices[$choice['plan_item_id']]);
            unset($day);
        }
        foreach ($days as &$day) {
            if ($day['stops'] !== []) {
                $leg = $this->leg($day['previous'], $day['hotel'], $matrix, $plan->mode);
                $leg['return_to_hotel'] = true;
                $day['legs'][] = $leg;
                $day['distance_km'] += $leg['distance_km'];
                $day['travel_minutes'] += $leg['duration_minutes'];
            }
            if ($day['hotel']) {
                $day['hotel'] = array_intersect_key($day['hotel'], array_flip(['name', 'hotel_id', 'lat', 'lng', 'options', 'times_assumed', 'rooms']));
            }
            unset($day['cursor'], $day['end'], $day['previous']);
        }
        unset($day);
        $snapshot = ['title' => $plan->title, 'destination' => $plan->destination->name, 'start_date' => $plan->start_date->toDateString(),
            'end_date' => $plan->end_date->toDateString(), 'pax' => $plan->pax, 'mode' => $plan->mode, 'generated_at' => now()->toIso8601String(),
            'graph_version' => config('services.routing.graph_version'), 'days' => $days, 'unscheduled' => $unscheduled,
            'selections' => $plan->items->map(fn ($item) => ['plan_item_id' => $item->id, 'type' => $item->item_type, 'item_id' => $item->item_id, 'options' => $item->options])->all(),
            'report' => [
                'method' => 'A greedy schedule favors lower forecast risk, then shorter directed route times and less crowded days. It is a practical heuristic, not proof of a globally optimal route.',
                'weather' => 'Forecasts apply to the destination area in Asia/Manila time. Each visit uses all overlapping 3-hour forecast intervals. Missing, expired or out-of-range forecasts remain provisional.',
                'travel' => 'Valhalla road/path estimates include return travel to the selected hotel. No live traffic, transport fares, boat journey or operator safety clearance is inferred.',
                'assumptions' => 'A 15-minute buffer separates visits; lunch is reserved from 12:00 to 13:00. Unknown hours, durations and hotel times are disclosed with each visit. Attractions are self-guided and are not booked at checkout.',
            ],
        ];

        return DB::transaction(function () use ($plan, $revision, $snapshot) {
            $locked = TripPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($locked->revision !== $revision) {
                throw ValidationException::withMessages(['plan' => 'The trip changed during generation. Generate it again with your current choices.']);
            }

            return $locked->itineraries()->create(['revision' => $revision, 'snapshot' => $snapshot]);
        });
    }

    private function scheduling(object $model, array $choice): array
    {
        $metadata = $choice['scheduling'];
        $assumptions = [];
        $duration = $metadata['duration_minutes'] ?? null;
        if (! $duration && preg_match('/(\d+(?:\.\d+)?)\s*(hour|hr|minute|min)/i', $choice['duration'] ?? '', $matches)) {
            $duration = (int) ceil((float) $matches[1] * (str_starts_with(strtolower($matches[2]), 'h') ? 60 : 1));
        }
        if (! $duration) {
            $duration = match (strtolower(trim($choice['duration'] ?? ''))) {
                'half day' => 240, 'full day' => 480, default => 90
            };
            $assumptions[] = 'Duration estimated at '.$duration.' minutes; confirm with the operator.';
        }
        if (empty($metadata['opens_at']) || empty($metadata['closes_at'])) {
            $assumptions[] = 'Opening hours are unverified; a 09:00–18:00 planning window is assumed.';
        }
        $environment = $metadata['environment'] ?? ($model instanceof ActivityModel ? $model->environment() : 'outdoor');
        if (empty($metadata['environment'])) {
            $assumptions[] = 'Indoor/outdoor suitability inferred from the listing; confirm before travel.';
        }

        return array_replace(['opens_at' => '09:00', 'closes_at' => '18:00', 'environment' => $environment],
            array_filter($metadata, fn ($value) => $value !== null && $value !== ''), ['duration_minutes' => $duration, 'assumptions' => $assumptions]);
    }

    private function candidate(array $choice, array $day, array $matrix, array $rawWeather): ?array
    {
        if (! $day['hotel'] || ! $this->routing->validPoint($choice['point']) || ! $this->routing->validPoint($day['hotel']['point'])) {
            return null;
        }
        if (isset($choice['options']['date']) && $choice['options']['date'] !== $day['date']) {
            return null;
        }
        $metadata = $choice['scheduling'];
        if (isset($metadata['weekdays']) && ! in_array(Carbon::parse($day['date'])->dayOfWeekIso, $metadata['weekdays'], true)) {
            return null;
        }
        $from = $day['previous']['point_index'];
        $to = $choice['point_index'];
        $base = $day['hotel']['point_index'];
        $seconds = $matrix['durations'][$from][$to] ?? null;
        $return = $matrix['durations'][$to][$base] ?? null;
        if ($seconds === null || $return === null) {
            return null;
        }
        $start = $day['cursor']->copy()->addMinutes((int) ceil($seconds / 60));
        $opens = Carbon::parse($day['date'].' '.$metadata['opens_at'], 'Asia/Manila');
        $closes = Carbon::parse($day['date'].' '.$metadata['closes_at'], 'Asia/Manila')->min($day['end']);
        $start = $start->max($opens);
        if (! empty($metadata['fixed_start_times'])) {
            $fixed = collect($metadata['fixed_start_times'])->map(fn ($time) => Carbon::parse($day['date'].' '.$time, 'Asia/Manila'))
                ->sortBy(fn ($time) => $time->timestamp)->first(fn ($time) => $time->gte($start));
            if (! $fixed) {
                return null;
            }
            $start = $fixed;
        }
        $finish = $start->copy()->addMinutes((int) $metadata['duration_minutes']);
        $lunchStart = Carbon::parse($day['date'].' 12:00', 'Asia/Manila');
        $lunchEnd = $lunchStart->copy()->addHour();
        if ($start->lt($lunchEnd) && $finish->gt($lunchStart)) {
            if (! empty($metadata['fixed_start_times']) || (int) $metadata['duration_minutes'] >= 240) {
                if (empty($metadata['includes_lunch'])) {
                    return null;
                }
            } else {
                $start = $lunchEnd;
                $finish = $start->copy()->addMinutes((int) $metadata['duration_minutes']);
            }
        }
        if ($finish->gt($closes) || $finish->copy()->addMinutes((int) ceil($return / 60))->gt($day['end'])) {
            return null;
        }
        $weather = $this->weatherEvidence($rawWeather, $start, $finish, $metadata['environment']);
        $travelWeather = $this->weatherEvidence($rawWeather, $day['cursor'], $start, 'outdoor');
        if ($weather['blocked'] || ($matrix['mode'] ?? 'walking') === 'walking' && $travelWeather['blocked']) {
            return null;
        }
        $weather['travel_weather'] = $travelWeather;

        $oldReturn = $matrix['durations'][$from][$base] ?? 0;
        $incrementalTravel = ($seconds + $return - $oldReturn) / 60;

        return ['start' => $start, 'finish' => $finish, 'weather' => $weather, 'incremental_travel_minutes' => $incrementalTravel,
            'score' => $weather['risk'] * 1000 + $incrementalTravel + count($day['stops']) + $day['day'] * 0.01];
    }

    private function weatherEvidence(array $raw, Carbon $start, Carbon $end, string $environment): array
    {
        $window = $this->weather->windowFromSlots($raw, $start, $end);
        $known = $window['covered'] && ! $window['stale'] && filled($window['fetched_at'])
            && Carbon::parse($window['fetched_at'])->gte(now()->subHours(6))
            && ! collect($window['slots'])->contains(fn ($slot) => ! is_numeric($slot['weather_id'] ?? null) || ! is_numeric($slot['wind_speed'] ?? null));
        $slots = collect($window['slots']);
        $rain = (float) ($slots->max('rain_1h') ?? 0);
        $wind = (float) ($slots->max('wind_speed') ?? 0);
        $gust = (float) ($slots->max('wind_gust') ?? 0);
        $pop = (float) ($slots->max('pop') ?? 0);
        $storm = $slots->contains(fn ($slot) => ($slot['weather_id'] ?? 800) >= 200 && ($slot['weather_id'] ?? 800) < 300);
        $outdoor = $environment !== 'indoor';

        return ['known' => $known, 'provisional' => ! $known, 'fetched_at' => $window['fetched_at'], 'stale' => $window['stale'],
            'slots' => $window['slots'], 'rain_mm_per_hour' => $known ? $rain : null, 'wind_m_per_second' => $known ? $wind : null,
            'rain_probability' => $known ? $pop : null,
            'blocked' => $known && $outdoor && ($storm || $rain > 5 || $wind > 10 || $gust > 15),
            'risk' => ! $known ? 0.8 : ($outdoor ? $rain + $wind / 10 + $pop : max(0, 1 - $pop)),
            'description' => ! $known ? 'Forecast unavailable for the complete visit; recheck closer to departure.' : ($slots->pluck('description')->filter()->unique()->join(', ') ?: 'Forecast data available.'),
        ];
    }

    private function leg(array $from, array $to, array $matrix, string $mode): array
    {
        $route = $this->routing->route($from['point'], $to['point'], $mode);

        return ['from' => $from['name'], 'to' => $to['name'], 'mode' => $mode,
            'distance_km' => $matrix['distances'][$from['point_index']][$to['point_index']] ?? 0,
            'duration_minutes' => (int) ceil(($matrix['durations'][$from['point_index']][$to['point_index']] ?? 0) / 60),
            'geometry' => $route['geometry'], 'geometry_status' => $route['status']];
    }

    private function reason(array $choice, array $previous, array $candidate, array $leg, string $mode): string
    {
        $weather = $candidate['weather'];
        $reason = sprintf('%s is %.2f km from %s by the mapped %s route (about %d minutes). This slot fits the visit and return travel within the day’s time window.',
            $choice['name'], $leg['distance_km'], $previous['name'], $mode, $leg['duration_minutes']);
        if ($weather['known']) {
            $reason .= sprintf(' During the visit, the forecast peaks at %.1f mm/h rain, %.1f m/s wind and %.0f%% rain probability.',
                $weather['rain_mm_per_hour'], $weather['wind_m_per_second'], $weather['rain_probability'] * 100);
            $reason .= $choice['scheduling']['environment'] === 'indoor' ? ' An indoor visit is less exposed to these conditions.' : ' No planner weather caution threshold is exceeded in this slot.';
            $alternative = collect($candidate['alternatives'])->filter(fn ($other) => $other['date'] !== $candidate['start']->toDateString() && $other['weather']['known'] && $other['weather']['risk'] > $weather['risk'])
                ->sortByDesc(fn ($other) => $other['weather']['risk'])->first();
            if ($alternative) {
                $reason .= $choice['scheduling']['environment'] === 'indoor'
                    ? sprintf(' Compared with %s, this indoor slot uses a rainier period (%.0f%% versus %.0f%% rain probability), leaving clearer time for outdoor visits.', $alternative['date'], $weather['rain_probability'] * 100, $alternative['weather']['rain_probability'] * 100)
                    : sprintf(' Compared with the earlier planning window on %s, this day has lower outdoor exposure: rain %.1f versus %.1f mm/h, wind %.1f versus %.1f m/s, and rain probability %.0f%% versus %.0f%%.', $alternative['date'], $weather['rain_mm_per_hour'], $alternative['weather']['rain_mm_per_hour'], $weather['wind_m_per_second'], $alternative['weather']['wind_m_per_second'], $weather['rain_probability'] * 100, $alternative['weather']['rain_probability'] * 100);
            }
        } else {
            $reason .= ' The day is provisional because a fresh forecast does not cover the entire visit.';
        }
        $reason .= ' Ordering also considers the extra travel needed to add this visit and return to the hotel, reducing repeated trips back to the same base.';

        return $reason.' '.implode(' ', $choice['scheduling']['assumptions']);
    }
}
