<?php

namespace App\Http\Requests\Admin;

use App\Models\Promotion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StorePromotionRequest extends FormRequest
{
    /**
     * Limits a new campaign starts with. They are not on the form, so saving
     * an edit leaves a campaign's existing values untouched.
     *
     * @var array<string, int>
     */
    public const LIMIT_DEFAULTS = ['per_traveler_limit' => 1, 'offer_valid_hours' => 72, 'award_weight' => 1];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('admin')->check();
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
            'win_percent' => ['nullable', 'integer', 'between:0,100'],
            'discount_type' => ['required', 'in:'.Promotion::TYPE_PERCENT.','.Promotion::TYPE_FIXED],
            'percent_off' => ['required_if:discount_type,'.Promotion::TYPE_PERCENT, 'nullable', 'integer', 'between:1,100'],
            'fixed_off' => ['required_if:discount_type,'.Promotion::TYPE_FIXED, 'nullable', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'],
            'max_discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:1', 'max:1000000'],
            'min_eligible' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
            'redeem_starts_at' => ['nullable', 'date'],
            'redeem_ends_at' => ['nullable', 'date', 'after:redeem_starts_at'],
            'duration_days' => ['nullable', 'integer', 'between:1,3650'],
            'destination_ids' => ['nullable', 'array'],
            'destination_ids.*' => ['integer', 'exists:destinations,id'],
            'total_redemption_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * The validated input as promotion columns: pesos become centavos
     * and admin-entered times are read as Philippine time. Columns not on the
     * form are left out so an edit cannot reset them.
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
            'name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false), 'win_percent' => (int) ($data['win_percent'] ?? Promotion::DEFAULT_WIN_PERCENT),
            'discount_type' => $data['discount_type'],
            'percent_off' => $percent ? (int) $data['percent_off'] : null,
            'fixed_off_cents' => $percent ? null : $cents('fixed_off'),
            'max_discount_cents' => $percent ? $cents('max_discount') : null,
            'min_eligible_cents' => $cents('min_eligible') ?? 0,
            'redeem_starts_at' => $manila('redeem_starts_at'), 'redeem_ends_at' => $manila('redeem_ends_at'),
            'duration_days' => $data['duration_days'] ?? null,
            'destination_ids' => ! empty($data['destination_ids']) ? array_values(array_map('intval', $data['destination_ids'])) : null,
            'total_redemption_limit' => $data['total_redemption_limit'] ?? null,
        ];
    }
}
