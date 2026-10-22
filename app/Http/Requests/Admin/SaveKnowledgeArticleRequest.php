<?php

namespace App\Http\Requests\Admin;

use App\Models\KnowledgeArticle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveKnowledgeArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * Publishing requires a responsible source and a review date; drafts may
     * be saved without them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $publishing = $this->input('status') === 'published';

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:3000'],
            'category' => ['required', Rule::in(array_keys(KnowledgeArticle::CATEGORIES))],
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'source_label' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'reviewed_at' => [$publishing ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date', $this->filled('reviewed_at') ? 'after:reviewed_at' : 'after:today'],
            'status' => ['required', Rule::in(KnowledgeArticle::STATUSES)],
            'reviewed_by' => [$publishing ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Edited text needs a fresh review before it is published again.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $article = $this->route('knowledgeArticle');
            if (! $article instanceof KnowledgeArticle || $this->input('status') !== 'published' || $validator->errors()->isNotEmpty()) {
                return;
            }
            $textChanged = $article->title !== $this->input('title') || $article->body !== $this->input('body');
            if ($textChanged && $this->date('reviewed_at')->toDateString() !== today()->toDateString()) {
                $validator->errors()->add('reviewed_at', 'Review the changed article and use today’s review date.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['source_label' => 'source', 'reviewed_at' => 'review date', 'reviewed_by' => 'reviewer', 'expires_at' => 'expiry date'];
    }
}
