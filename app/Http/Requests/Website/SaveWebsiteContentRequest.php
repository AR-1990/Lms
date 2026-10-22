<?php

namespace App\Http\Requests\Website;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWebsiteContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-website');
    }

    /**
     * Rules for the content type in the URL (stats, testimonials, posts, faqs).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $common = ['sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'], 'is_active' => ['sometimes', 'boolean']];

        return match ($this->route('type')) {
            'stats' => [
                'label' => ['required', 'string', 'max:100'],
                'value' => ['required', 'integer', 'min:0', 'max:100000000'],
                'suffix' => ['nullable', 'string', 'max:10'],
                'icon' => ['nullable', 'string', 'max:40'],
                ...$common,
            ],
            'testimonials' => [
                'name' => ['required', 'string', 'max:150'],
                'role_title' => ['nullable', 'string', 'max:150'],
                'quote' => ['required', 'string', 'max:1500'],
                ...$common,
            ],
            'posts' => [
                'category_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'news_category')->whereNull('deleted_at')],
                'title' => ['required', 'string', 'max:255'],
                'excerpt' => ['nullable', 'string', 'max:500'],
                'body' => ['nullable', 'string', 'max:20000'],
                'published_on' => ['nullable', 'date_format:Y-m-d'],
                'read_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
                'is_featured' => ['sometimes', 'boolean'],
                'show_in_ticker' => ['sometimes', 'boolean'],
                'is_published' => ['sometimes', 'boolean'],
            ],
            'faqs' => [
                'question' => ['required', 'string', 'max:255'],
                'answer' => ['required', 'string', 'max:3000'],
                ...$common,
            ],
            default => abort(404),
        };
    }
}
