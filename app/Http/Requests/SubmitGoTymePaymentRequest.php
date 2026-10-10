<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitGoTymePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sender_ref' => ['required', 'string', 'max:50', 'regex:/[A-Za-z0-9]/'],
            'claimed_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
        ];
    }
}
