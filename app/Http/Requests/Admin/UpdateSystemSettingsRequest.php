<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSystemSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $section = $this->route('section');
        $fields = match ($section) {
            'business' => [
                'display_name' => ['required', 'string', 'max:120'],
                'support_email' => ['nullable', 'email', 'max:254'],
                'phone' => ['nullable', 'string', 'max:40'],
                'address' => ['nullable', 'string', 'max:500'],
                'facebook_url' => ['nullable', 'url:https', 'max:2048'],
                'instagram_url' => ['nullable', 'url:https', 'max:2048'],
                'website_url' => ['nullable', 'url:https', 'max:2048'],
            ],
            'accounts' => ['grace_period' => ['required', 'boolean']],
            'payments' => [
                'installments_enabled' => ['required', 'boolean'],
                'initial_window_hours' => ['required', 'integer', 'between:1,720'],
                'deposit_min_advance_days' => ['sometimes', 'required', 'integer', 'between:1,90'],
                'balance_lead_days' => ['required', 'integer', 'between:1,90'],
                'undated_balance_days' => ['required', 'integer', 'between:1,90'],
                'service_cutoff_hours' => ['required', 'integer', 'between:1,72'],
            ],
            'chatbot' => [
                'daily_limit' => ['required', 'integer', 'between:1,1000'],
                'minute_limit' => ['required', 'integer', 'between:1,10'],
            ],
            'support' => [
                'accept_requests' => ['required', 'boolean'],
                'enforce_hours' => ['required', 'boolean'],
                'unavailable_message' => ['required', 'string', 'max:500'],
                'schedule' => ['required', 'array:mon,tue,wed,thu,fri,sat,sun', 'size:7'],
            ],
            'notifications' => [
                'recipient_mode' => ['required', Rule::in(['all', 'selected'])],
                'admin_ids' => ['nullable', 'array', 'max:50'],
                'booking_requested' => ['required', 'boolean'],
                'payment_submitted' => ['required', 'boolean'],
                'cancellation_requested' => ['required', 'boolean'],
                'support_requested' => ['required', 'boolean'],
            ],
            default => [],
        };
        $rules = [
            'section' => ['required', Rule::in([$section])],
            'revision' => ['required', 'integer', 'min:0'],
            'values' => ['required', 'array:'.implode(',', array_keys($fields))],
        ];
        foreach ($fields as $field => $fieldRules) {
            $rules['values.'.$field] = $fieldRules;
        }
        if ($section === 'support') {
            foreach (array_keys(config('system_settings.support.schedule')) as $day) {
                $rules["values.schedule.$day"] = ['required', 'array:enabled,all_day,opens,closes'];
                $rules["values.schedule.$day.enabled"] = ['required', 'boolean'];
                $rules["values.schedule.$day.all_day"] = ['required', 'boolean'];
                $rules["values.schedule.$day.opens"] = ['nullable', 'date_format:H:i'];
                $rules["values.schedule.$day.closes"] = ['nullable', 'date_format:H:i'];
            }
        }
        if ($section === 'notifications') {
            $rules['values.admin_ids.*'] = ['integer', 'distinct', Rule::exists('admins', 'id')];
        }

        return $rules;
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->route('section') === 'payments' && (int) $this->input('values.undated_balance_days') * 24 <= (int) $this->input('values.initial_window_hours')) {
                $validator->errors()->add('values.undated_balance_days', 'The balance window for flexible-date bookings must exceed the initial payment window.');
            }
            if ($this->route('section') === 'support') {
                $openDays = 0;
                foreach ($this->input('values.schedule', []) as $day => $hours) {
                    if (filter_var($hours['enabled'], FILTER_VALIDATE_BOOLEAN)) {
                        $openDays++;
                        if (! filter_var($hours['all_day'], FILTER_VALIDATE_BOOLEAN) &&
                            (empty($hours['opens']) || empty($hours['closes']) || $hours['closes'] <= $hours['opens'])) {
                            $validator->errors()->add("values.schedule.$day.closes", 'Closing time must be after opening time. Use all day for 24-hour support.');
                        }
                    }
                }
                if ($this->boolean('values.enforce_hours') && $openDays === 0) {
                    $validator->errors()->add('values.schedule', 'Choose at least one operating day.');
                }
            }
            if ($this->route('section') === 'notifications' && $this->input('values.recipient_mode') === 'selected') {
                $enabled = collect($this->only('values')['values'])->except(['admin_ids', 'recipient_mode'])->contains(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN));
                if ($enabled && empty($this->input('values.admin_ids'))) {
                    $validator->errors()->add('values.admin_ids', 'Select at least one admin to receive enabled email alerts.');
                }
            }
        }];
    }
}
