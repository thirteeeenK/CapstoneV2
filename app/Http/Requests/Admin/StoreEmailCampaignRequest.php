<?php

namespace App\Http\Requests\Admin;

use App\Models\DestinationModel;
use App\Models\EmailCampaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreEmailCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * Content is plain text, escaped when the email is rendered; the button
     * only ever links to a page on this site.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:150'],
            'preheader' => ['nullable', 'string', 'max:150'],
            'headline' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_image' => ['nullable', 'boolean'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_target' => ['required', Rule::in(array_keys(EmailCampaign::CTA_TARGETS))],
            'cta_target_id' => [
                Rule::requiredIf(fn () => in_array($this->input('cta_target'), ['destination', 'package'], true)), 'nullable', 'integer',
                $this->input('cta_target') === 'package' ? Rule::exists('packages', 'id') : Rule::exists('destinations', 'id'),
            ],
            'cta_path' => ['required_if:cta_target,custom', 'nullable', 'string', 'max:255', 'regex:/^\/(?!\/)[A-Za-z0-9\/_\-.~?=&%]*$/'],
            'send_mode' => ['required', Rule::in(EmailCampaign::MODES)],
            'trigger' => ['required_if:send_mode,'.EmailCampaign::MODE_AUTOMATIC, 'nullable', Rule::in(array_keys(EmailCampaign::TRIGGERS))],
            'scheduled_at' => [
                Rule::requiredIf(fn () => $this->input('send_mode') === EmailCampaign::MODE_SCHEDULED && $this->input('intent') === 'schedule'),
                'nullable', 'date_format:Y-m-d\TH:i',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && $this->input('intent') === 'schedule' && Carbon::parse($value, 'Asia/Manila')->lte(now())) {
                        $fail('Choose a send time in the future (Philippine time).');
                    }
                },
            ],
            'promotion_id' => ['nullable', 'integer', Rule::exists('promotions', 'id')],
            'audience' => ['nullable', 'array:segment,destinations,traveler_types,inactive_days'],
            'audience.segment' => ['nullable', Rule::in(array_keys(EmailCampaign::SEGMENTS))],
            'audience.destinations' => ['nullable', 'array', 'max:50'],
            'audience.destinations.*' => ['string', Rule::in(DestinationModel::query()->pluck('name')->all())],
            'audience.traveler_types' => ['nullable', 'array', 'max:20'],
            'audience.traveler_types.*' => ['string', 'max:100'],
            'audience.inactive_days' => ['nullable', 'integer', 'between:7,730'],
            'intent' => ['required', Rule::in(['draft', 'schedule', 'activate'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cta_path.regex' => 'The button link must be a page on this site starting with "/", for example /destinations.',
            'cta_target_id.required' => 'Choose which destination or tour package the button opens.',
            'trigger.required_if' => 'Choose what makes this automatic campaign send.',
            'scheduled_at.required' => 'Choose when this campaign should send.',
            'audience.destinations.*.in' => 'Choose destinations from the list.',
        ];
    }

    /**
     * The campaign's saved attributes, without the image or the save intent.
     *
     * @return array<string, mixed>
     */
    public function campaignAttributes(): array
    {
        $data = $this->validated();
        $audience = $data['audience'] ?? [];
        $mode = $data['send_mode'];

        return [
            'name' => $data['name'], 'subject' => $data['subject'], 'preheader' => $data['preheader'] ?? null,
            'headline' => $data['headline'], 'body' => $data['body'], 'cta_label' => $data['cta_label'] ?? null,
            'cta_target' => $data['cta_target'],
            'cta_target_id' => in_array($data['cta_target'], ['destination', 'package'], true) ? ($data['cta_target_id'] ?? null) : null,
            'cta_path' => $data['cta_target'] === 'custom' ? $data['cta_path'] : null,
            'send_mode' => $mode,
            'trigger' => $mode === EmailCampaign::MODE_AUTOMATIC ? $data['trigger'] : null,
            'scheduled_at' => $mode === EmailCampaign::MODE_SCHEDULED && ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at'], 'Asia/Manila') : null,
            'promotion_id' => $data['promotion_id'] ?? null,
            'audience' => [
                'segment' => $audience['segment'] ?? 'all',
                'destinations' => array_values($audience['destinations'] ?? []),
                'traveler_types' => array_values($audience['traveler_types'] ?? []),
                'inactive_days' => (int) ($audience['inactive_days'] ?? 90),
            ],
        ];
    }
}
