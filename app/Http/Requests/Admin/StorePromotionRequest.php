<?php

namespace App\Http\Requests\Admin;

use App\Models\Promotion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StorePromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code_prefix' => strtoupper(trim((string) $this->input('code_prefix')))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'code_prefix' => ['required', 'regex:/^[A-Z0-9]{2,12}$/'],
            'discount_type' => ['required', 'in:'.Promotion::TYPE_PERCENT.','.Promotion::TYPE_FIXED],
            'percent_off' => ['required_if:discount_type,'.Promotion::TYPE_PERCENT, 'nullable', 'integer', 'between:1,100'],
            'fixed_off' => ['required_if:discount_type,'.Promotion::TYPE_FIXED, 'nullable', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'],
            'max_discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'],
            'min_eligible' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
            'issue_starts_at' => ['nullable', 'date'],
            'issue_ends_at' => ['nullable', 'date', 'after:issue_starts_at'],
            'redeem_starts_at' => ['nullable', 'date'],
            'redeem_ends_at' => ['nullable', 'date', 'after:redeem_starts_at'],
            'travel_starts_on' => ['nullable', 'date'],
            'travel_ends_on' => ['nullable', 'date', 'after_or_equal:travel_starts_on'],
            'destination_ids' => ['nullable', 'array'],
            'destination_ids.*' => ['integer', 'exists:destinations,id'],
            'min_nights' => ['nullable', 'integer', 'between:1,30'],
            'max_nights' => ['nullable', 'integer', 'between:1,30', 'gte:min_nights'],
            'min_pax' => ['nullable', 'integer', 'between:1,20'],
            'max_pax' => ['nullable', 'integer', 'between:1,20', 'gte:min_pax'],
            'total_redemption_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_traveler_limit' => ['required', 'integer', 'between:1,50'],
            'codes_per_traveler_per_day' => ['required', 'integer', 'between:1,50'],
            'offer_valid_hours' => ['required', 'integer', 'between:1,720'],
            'award_weight' => ['required', 'integer', 'between:0,100'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The validated input as promotion columns: pesos become centavos and
     * admin-entered times are read as Philippine time.
     *
     * @return array<string, mixed>
     */
    public function promotionAttributes(): array
    {
        $data = $this->validated();
        $percent = $data['discount_type'] === Promotion::TYPE_PERCENT;
        $cents = fn (string $key) => isset($data[$key]) && $data[$key] !== '' ? (int) round((float) $data[$key] * 100) : null;
        $manila = fn (string $key) => ! empty($data[$key]) ? Carbon::parse($data[$key], 'Asia/Manila') : null;

        return [
            'name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false), 'code_prefix' => $data['code_prefix'],
            'discount_type' => $data['discount_type'],
            'percent_off' => $percent ? (int) $data['percent_off'] : null,
            'fixed_off_cents' => $percent ? null : $cents('fixed_off'),
            'max_discount_cents' => $percent ? $cents('max_discount') : null,
            'min_eligible_cents' => $cents('min_eligible') ?? 0,
            'issue_starts_at' => $manila('issue_starts_at'), 'issue_ends_at' => $manila('issue_ends_at'),
            'redeem_starts_at' => $manila('redeem_starts_at'), 'redeem_ends_at' => $manila('redeem_ends_at'),
            'travel_starts_on' => $data['travel_starts_on'] ?? null, 'travel_ends_on' => $data['travel_ends_on'] ?? null,
            'destination_ids' => ! empty($data['destination_ids']) ? array_values(array_map('intval', $data['destination_ids'])) : null,
            'min_nights' => $data['min_nights'] ?? null, 'max_nights' => $data['max_nights'] ?? null,
            'min_pax' => $data['min_pax'] ?? null, 'max_pax' => $data['max_pax'] ?? null,
            'total_redemption_limit' => $data['total_redemption_limit'] ?? null, 'per_traveler_limit' => (int) $data['per_traveler_limit'],
            'codes_per_traveler_per_day' => (int) $data['codes_per_traveler_per_day'], 'offer_valid_hours' => (int) $data['offer_valid_hours'],
            'award_weight' => (int) $data['award_weight'], 'terms' => $data['terms'] ?? null,
        ];
    }
}
