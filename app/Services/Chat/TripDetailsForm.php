<?php

namespace App\Services\Chat;

use App\Models\DestinationModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TripDetailsForm
{
    /** @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    public function build(array $state): ?array
    {
        $tasks = array_values(array_filter($state['tasks'], fn (array $task): bool => ! in_array($task['status'], ['cancelled', 'suspended'], true)));
        if ($tasks === []) {
            return null;
        }
        $questionIds = $state['active_question']['task_ids'] ?? [];
        $selected = collect($tasks)->first(fn (array $task): bool => in_array($task['persistent_id'], $questionIds, true)) ?? $tasks[array_key_last($tasks)];
        $tasks = array_values(array_filter($tasks, fn (array $task): bool => $task['scope_id'] === $selected['scope_id']));
        $values = [];
        foreach ($tasks as $task) {
            $values = array_replace($values, array_filter($task['constraints'], fn (mixed $value): bool => $value !== null && $value !== []));
        }
        foreach (['budget_basis', 'budget_period', 'budget_coverage'] as $key) {
            $values[$key] = $selected[$key] ?? null;
        }
        $values['dates_undecided'] = ($selected['slots']['dates']['status'] ?? null) === 'undecided';
        $types = array_column($tasks, 'type');

        return ['version' => 1, 'id' => (string) Str::uuid(), 'revision' => $state['revision'], 'scope_id' => $selected['scope_id'],
            'request' => implode(' + ', array_unique($types)),
            'needs_dates' => array_intersect($types, [IntentRouter::ITINERARY_QUERY, IntentRouter::AVAILABILITY_QUERY, IntentRouter::ROOM_SEARCH, IntentRouter::HOTEL_SEARCH]) !== [],
            'needs_duration' => in_array(IntentRouter::ITINERARY_QUERY, $types, true),
            'values' => $values, 'expanded' => $state['active_question'] !== null,
            'destinations' => DestinationModel::query()->orderBy('name')->get(['id', 'name'])->toArray()];
    }

    /** @param array<string, mixed> $form
     * @param  array<string, mixed>  $input
     * @return array{values: array<string, mixed>, message: string}
     */
    public function validate(array $form, array $input): array
    {
        Validator::make(['trip_details' => $input], ['trip_details.dates_undecided' => ['sometimes', 'boolean']])->validate();
        $effective = array_replace($form['values'] ?? [], $input);
        $undecided = filter_var($effective['dates_undecided'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requiresDates = ! $undecided && ! empty($form['needs_dates']) && (! empty($form['needs_duration']) || array_key_exists('dates_undecided', $input));
        if ($undecided) {
            unset($input['check_in_date'], $input['check_out_date']);
            $input['dates_undecided'] = true;
            if (! empty($form['needs_duration'])) {
                $input['days'] = $effective['days'] ?? null;
                Validator::make(['trip_details' => $input], ['trip_details.days' => ['required', 'integer', 'between:1,30']], $this->messages())->validate();
            }
        } elseif ($requiresDates || ! empty($effective['check_in_date']) || ! empty($effective['check_out_date'])) {
            $input['check_in_date'] = $effective['check_in_date'] ?? null;
            $input['check_out_date'] = $effective['check_out_date'] ?? null;
            Validator::make(['trip_details' => $input], [
                'trip_details.check_in_date' => ['required'],
                'trip_details.check_out_date' => ['required'],
            ], $this->messages())->validate();
            unset($input['days']);
        }
        if (empty($input['dates_undecided']) && (array_key_exists('check_in_date', $input) || array_key_exists('check_out_date', $input))) {
            foreach (['check_in_date', 'check_out_date'] as $key) {
                if (! array_key_exists($key, $input) && ! empty($form['values'][$key])) {
                    $input[$key] = $form['values'][$key];
                }
            }
        }
        $data = Validator::make(['trip_details' => $input], [
            'trip_details' => ['required', 'array:destination_id,pax,days,check_in_date,check_out_date,dates_undecided,max_price,budget_basis,budget_period,budget_coverage,preferences,notes'],
            'trip_details.destination_id' => ['sometimes', 'required', 'integer', Rule::in(array_column($form['destinations'], 'id'))],
            'trip_details.pax' => ['sometimes', 'nullable', 'integer', 'between:1,20'],
            'trip_details.days' => ['sometimes', 'nullable', 'integer', 'between:1,30'],
            'trip_details.check_in_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'trip_details.check_out_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:trip_details.check_in_date'],
            'trip_details.dates_undecided' => ['sometimes', 'boolean'],
            'trip_details.max_price' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:10000000'],
            'trip_details.budget_basis' => ['sometimes', 'nullable', Rule::in(['unknown', 'trip_total', 'per_person', 'per_night', 'per_activity'])],
            'trip_details.budget_period' => ['sometimes', 'nullable', Rule::in(['total', 'per_night'])],
            'trip_details.budget_coverage' => ['sometimes', 'nullable', Rule::in(['all_inclusive', 'lodging', 'activities'])],
            'trip_details.preferences' => ['sometimes', 'array', 'max:6'],
            'trip_details.preferences.*' => ['string', Rule::in(['adventure', 'relaxing', 'beachfront', 'budget-friendly', 'family-friendly', 'indoor'])],
            'trip_details.notes' => ['sometimes', 'nullable', 'string', 'max:300'],
        ], $this->messages())->validate()['trip_details'];
        foreach (['destination_id', 'pax', 'days'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        if (isset($data['max_price'])) {
            $data['max_price'] = (float) $data['max_price'];
        }
        $values = $data;
        if (($data['budget_basis'] ?? $form['values']['budget_basis'] ?? null) === 'trip_total'
            && ($data['budget_period'] ?? $form['values']['budget_period'] ?? null) === 'per_night') {
            $values['budget_basis'] = 'per_night';
        }
        if (($values['budget_basis'] ?? null) === 'per_night') {
            $values['budget_period'] = 'per_night';
        }
        if (isset($data['destination_id'])) {
            $values['destination_name'] = collect($form['destinations'])->firstWhere('id', (int) $data['destination_id'])['name'];
        }
        if (! empty($data['dates_undecided'])) {
            $values['check_in_date'] = $values['check_out_date'] = null;
            if (! empty($data['days'])) {
                $values['nights'] = max(0, $data['days'] - 1);
            }
        } elseif (! empty($data['check_in_date']) || ! empty($data['check_out_date'])) {
            Validator::make(['trip_details' => $data], ['trip_details.check_in_date' => ['required'], 'trip_details.check_out_date' => ['required']], $this->messages())->validate();
            $days = (int) Carbon::parse($data['check_in_date'])->diffInDays(Carbon::parse($data['check_out_date'])) + 1;
            if ($days < 2 || $days > 30) {
                throw ValidationException::withMessages(['trip_details.check_out_date' => $days < 2
                    ? 'Departure must be at least one night after arrival.'
                    : 'Those dates cover '.$days.' days. Plan up to 30 days at a time.']);
            }
            $values['days'] = $days;
            $values['nights'] = $days - 1;
        } elseif (! empty($data['days'])) {
            $values['nights'] = max(0, (int) $data['days'] - 1);
        }
        $summary = [];
        if (! empty($values['destination_name'])) {
            $summary[] = $values['destination_name'];
        }
        if (! empty($values['pax'])) {
            $summary[] = $values['pax'].' travelers';
        }
        if (! empty($values['days'])) {
            $summary[] = $values['days'].' days, '.($values['nights'] ?? max(0, $values['days'] - 1)).' nights';
        }
        if (! empty($values['dates_undecided'])) {
            $summary[] = 'dates undecided';
        } elseif (! empty($values['check_in_date'])) {
            $summary[] = $values['check_in_date'].' to '.$values['check_out_date'];
        }
        if (! empty($values['max_price'])) {
            $basis = match ($values['budget_basis'] ?? $form['values']['budget_basis'] ?? 'unknown') {
                'per_person' => 'per person', 'per_night' => 'for the group per night', 'per_activity' => 'per activity', 'trip_total' => 'for the whole group', default => 'budget',
            };
            if (($values['budget_basis'] ?? null) === 'per_person' && ! empty($values['budget_period'])) {
                $basis .= $values['budget_period'] === 'per_night' ? ' per night' : ' for the whole stay';
            }
            $summary[] = '₱'.number_format((float) $values['max_price']).' '.$basis;
        }
        if (! empty($values['budget_coverage'])) {
            $summary[] = match ($values['budget_coverage']) {
                'lodging' => 'accommodation only', 'activities' => 'activities only', default => 'hotel and activities',
            };
        }
        if (! empty($values['preferences'])) {
            $summary[] = implode(', ', $values['preferences']);
        }
        if (! empty($values['notes'])) {
            $summary[] = $values['notes'];
        }

        return ['values' => $values, 'message' => 'Trip details: '.($summary === [] ? 'updated preferences' : implode(' · ', $summary))];
    }

    /**
     * Readable, control-anchored messages for the consolidated trip form, so
     * the chat error region never shows internal field paths.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'trip_details.array' => 'Please re-open the trip details form and submit it again.',
            'trip_details.destination_id.required' => 'Choose a destination.',
            'trip_details.destination_id.in' => 'Choose a destination from the list.',
            'trip_details.pax.integer' => 'Enter the number of travelers as a whole number.',
            'trip_details.pax.between' => 'Travelers must be between 1 and 20.',
            'trip_details.days.required' => 'Enter how many days the trip should last.',
            'trip_details.days.integer' => 'Enter the trip length as a whole number of days.',
            'trip_details.days.between' => 'Trip length must be between 1 and 30 days.',
            'trip_details.check_in_date.required' => 'Choose an arrival date, or tick "Dates not decided yet".',
            'trip_details.check_out_date.required' => 'Choose a departure date, or tick "Dates not decided yet".',
            'trip_details.check_in_date.date_format' => 'Enter the arrival date as a calendar date.',
            'trip_details.check_out_date.date_format' => 'Enter the departure date as a calendar date.',
            'trip_details.check_in_date.after_or_equal' => 'Arrival cannot be in the past.',
            'trip_details.check_out_date.after' => 'Departure must be after arrival.',
            'trip_details.max_price.numeric' => 'Enter the budget as an amount in pesos.',
            'trip_details.max_price.gt' => 'Enter a budget above zero, or leave it blank.',
            'trip_details.max_price.max' => 'Enter a smaller budget amount.',
            'trip_details.preferences.max' => 'Choose up to six preferences.',
            'trip_details.notes.max' => 'Keep the notes to 300 characters or fewer.',
        ];
    }
}
