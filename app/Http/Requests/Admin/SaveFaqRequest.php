<?php

namespace App\Http\Requests\Admin;

use App\Models\Faq;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveFaqRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'show_on_landing' => ['sometimes', 'boolean'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'reviewed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reviewed_by' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A new or changed answer that SunnyBot will use needs a responsible
     * source and a fresh review date. Unchanged legacy FAQs keep working
     * without them (they are labelled as unreviewed instead).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->boolean('is_active') || $validator->errors()->isNotEmpty()) {
                return;
            }
            $faq = $this->route('id') ? Faq::find($this->route('id')) : null;
            $claimChanged = $faq === null || $faq->question !== $this->input('question') || $faq->answer !== $this->input('answer');
            if (! $claimChanged && $faq?->is_active) {
                return;
            }
            if (! $this->filled('source_label')) {
                $validator->errors()->add('source_label', 'Record who is responsible for this answer before SunnyBot can use it.');
            }
            if (! $this->filled('reviewed_at')) {
                $validator->errors()->add('reviewed_at', 'Record the review date before SunnyBot can use this answer.');
            } elseif ($claimChanged && $faq !== null && $this->date('reviewed_at')->toDateString() !== today()->toDateString()) {
                $validator->errors()->add('reviewed_at', 'Review the changed answer and use today’s review date.');
            }
        }];
    }
}
