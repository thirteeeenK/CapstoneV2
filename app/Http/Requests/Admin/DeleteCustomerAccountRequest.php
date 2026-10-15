<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteCustomerAccountRequest extends FormRequest
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
        return [
            'mode' => ['required', Rule::in(['scheduled', 'permanent'])],
            'confirm_email' => ['required', 'email', 'max:254'],
            'reason' => ['required', 'string', 'max:500'],
            'target_id' => ['nullable', 'integer', Rule::in([(int) $this->route('id')])],
            'target_name' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', Rule::in(['all', 'pending', 'flagged', 'warned', 'temporary', 'permanent'])],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
