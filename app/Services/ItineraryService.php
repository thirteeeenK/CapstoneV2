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
    public function __construct(private RoutingService $routing, private WeatherService $weather, private TripPlannerService $planner, private GeminiService $gemini) {}

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
                $events[] = ['name' => 'Check in at '.$stay['name'], 'time' => $stay['check_in_time'], 'assumed' => $stay['times_assumed'], 'departure' => false];
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
                        $events[] = ['name' => 'Check out of '.$departing['name'].' and transfer to '.$stay['name'].' ('.$legs[0]['duration_minutes'].' min mapped travel)', 'time' => $departing['check_out_time'], 'assumed' => $departing['times_assumed'], 'departure' => false];
                        $arrival = Carbon::parse($key.' '.$departing['check_out_time'], 'Asia/Manila')->addSeconds($travel)->addMinutes(30);
                        $start = $start->max($arrival);
                    }
                }
            }
            if ($stay && $stay['options']['check_out'] === $key) {
                $end = Carbon::parse($key.' '.$stay['check_out_time'], 'Asia/Manila');
                $events[] = ['name' => 'Check out of '.$stay['name'], 'time' => $stay['check_out_time'], 'assumed' => $stay['times_assumed'], 'departure' => true];
            }
            $days[] = ['day' => count($days) + 1, 'date' => $key, 'label' => $date->format('l, F j'), 'hotel' => $stay,
                'cursor' => $start, 'end' => $end, 'previous' => $stay, 'events' => $events, 'stops' => [], 'legs' => $legs,
                'distance_km' => array_sum(array_column($legs, 'distance_km')), 'travel_minutes' => array_sum(array_column($legs, 'duration_minutes'))];
        }

        while ($choices !== []) {
            $best = null;
            foreach ($choices as $choice) {
                foreach ($days as $index => $day) {
                    $candidate = $this->candidate($choice, $day, $matrix, $rawWeather);
                    if ($candidate && ($best === null || $candidate['score'] < $best['score'])) {
                        $best = $candidate + ['day_index' => $index, 'choice' => $choice];
                    }
                }
            }
            if ($best === null) {
                foreach ($choices as $choice) {
                    $routable = collect($stays)->contains(fn ($stay) => ($matrix['durations'][$stay['point_index']][$choice['point_index']] ?? null) !== null
                        && ($matrix['durations'][$choice['point_index']][$stay['point_index']] ?? null) !== null);
                    $unscheduled[] = ['plan_item_id' => $choice['plan_item_id'], 'type' => $choice['type'], 'name' => $choice['name'],
                        'reason' => ! $this->routing->validPoint($choice['point'])
                            ? 'We do not have an exact location for this yet, so we could not map it. You can still add it to your trip and arrange it with your hotel or a local operator.'
                            : (in_array($matrix['status'] ?? '', ['not_configured', 'unavailable'], true)
                                ? 'Our map service is temporarily unavailable, so we could not plot a route. Your choices are saved — generate again in a little while.'
                                : (! $routable
                                    ? 'Our map provider does not have a route to this spot yet, so we could not place it on a day. You can still add it to your trip and arrange it with your hotel or a local operator.'
                                    : 'There was not enough free time to fit this on your trip dates. You can still add it and arrange the timing yourself, or free up time by removing another visit.'))];
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
        $reportContext = [
            'title' => $plan->title, 'destination' => $plan->destination->name,
            'start_date' => $plan->start_date->toDateString(), 'end_date' => $plan->end_date->toDateString(),
            'pax' => $plan->pax, 'mode' => $plan->mode,
            'days' => array_map(fn ($day) => [
                'date' => $day['date'],
                'stops' => array_values(array_map(fn ($stop) => $stop['name'], $day['stops'])),
                'distance_km' => round($day['distance_km'], 2),
                'travel_minutes' => $day['travel_minutes'],
                'provisional' => (bool) collect($day['stops'])->contains(fn ($stop) => $stop['weather']['provisional'] ?? false),
            ], $days),
            'unscheduled' => array_map(fn ($item) => ['name' => $item['name'], 'reason' => $item['reason']], $unscheduled),
        ];
        $snapshot = ['title' => $plan->title, 'destination' => $plan->destination->name, 'start_date' => $plan->start_date->toDateString(),
            'end_date' => $plan->end_date->toDateString(), 'pax' => $plan->pax, 'mode' => $plan->mode, 'generated_at' => now()->toIso8601String(),
            'graph_version' => config('services.routing.graph_version'), 'days' => $days, 'unscheduled' => $unscheduled,
            'selections' => $plan->items->map(fn ($item) => ['plan_item_id' => $item->id, 'type' => $item->item_type, 'item_id' => $item->item_id, 'options' => $item->options])->all(),
            'report' => $this->gemini->generateTripReport($reportContext),
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
        $travel = $mode === 'driving' ? 'drive' : 'walk';
        $reason = sprintf('We placed %s here because it is about %d minutes (%.2f km) from %s by %s, leaving enough time to enjoy it and still get back to your hotel the same day.',
            $choice['name'], $leg['duration_minutes'], $leg['distance_km'], $previous['name'], $travel);

        if ($candidate['weather']['known']) {
            $reason .= $choice['scheduling']['environment'] === 'indoor'
                ? ' Since this is an indoor activity, we scheduled it on a day with a higher chance of rain, so your sunnier days stay free for outdoor plans.'
                : ' This is one of the better days for being outdoors on your trip, which is why an outdoor activity like this one fits here.';
        } else {
            $reason .= ' The full weather forecast for this day is not available yet, so please check the conditions again closer to your trip.';
        }

        $reason .= ' We also grouped nearby stops together so you spend less time travelling back and forth.';

        return trim($reason.' '.implode(' ', $choice['scheduling']['assumptions']));
    }
}
