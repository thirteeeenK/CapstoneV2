<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdminBookingRequest extends FormRequest
{
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
            'is_walk_in' => ['required', 'boolean'],
            'user_id' => ['nullable', 'required_if:is_walk_in,false', 'exists:users,id'],
            'auto_create_account' => ['nullable', 'boolean'],
            'temporary_password' => ['nullable', 'string', 'min:6', 'max:100'],
            'booking_source' => ['required', 'string', 'in:admin_walk_in,admin_phone,admin_concierge'],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['required', 'string', 'regex:/^\d{11}$/', 'max:30'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'string', 'in:room,package,activity,addon'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.selected_pax' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.check_in_date' => ['nullable', 'date'],
            'items.*.check_out_date' => ['nullable', 'date'],
            'guest_manifest' => ['nullable', 'array'],
            'guest_manifest.*.full_name' => ['nullable', 'string', 'max:150'],
            'guest_manifest.*.category' => ['nullable', 'string', 'max:50'],
            'admin_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'admin_surcharge_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'price_adjustment_reason' => ['nullable', 'string', 'max:500'],
            'initial_status' => ['required', 'string', 'in:pending,approved,paid'],
            'payment_method' => ['nullable', 'required_if:initial_status,paid', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'The mobile phone number must be exactly 11 digits.',
        ];
    }

    /**
     * Configure additional validator logic.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $discount = (float) $this->input('admin_discount_amount', 0);
            $surcharge = (float) $this->input('admin_surcharge_amount', 0);
            $reason = trim((string) $this->input('price_adjustment_reason', ''));

            if (($discount > 0 || $surcharge > 0) && empty($reason)) {
                $validator->errors()->add('price_adjustment_reason', 'A reason is required when applying an admin discount or surcharge.');
            }

            $today = today()->format('Y-m-d');

            foreach ((array) $this->input('items', []) as $index => $item) {
                $type = $item['item_type'] ?? null;
                $checkIn = $item['check_in_date'] ?? null;
                $checkOut = $item['check_out_date'] ?? null;

                if ($checkIn && $checkOut && $checkOut <= $checkIn) {
                    $validator->errors()->add("items.{$index}.check_out_date", 'Check-out date must be after check-in date.');
                }

                if ($type !== 'room') {
                    continue;
                }

                if (empty($checkIn) || empty($checkOut)) {
                    $validator->errors()->add("items.{$index}.check_in_date", 'Room bookings require check-in and check-out dates.');

                    continue;
                }

                if ($checkIn < $today) {
                    $validator->errors()->add("items.{$index}.check_in_date", 'Room check-in date cannot be in the past.');
                }

                $room = RoomType::find($item['item_id'] ?? null);
                $selectedPax = (int) ($item['selected_pax'] ?? 1);
                if ($room && $selectedPax > (int) $room->max_occupancy) {
                    $validator->errors()->add(
                        "items.{$index}.selected_pax",
                        "Room '{$room->room_name}' allows at most {$room->max_occupancy} guest(s)."
                    );
                }
            }
        });
    }
}
