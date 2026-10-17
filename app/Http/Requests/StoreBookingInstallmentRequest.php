<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() || auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::in(['full', 'deposit', 'balance'])],
            'gateway' => ['required', Rule::in(['paymongo', 'stripe', 'qrph', 'gotyme'])],
        ];
    }
}
