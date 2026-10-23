<?php

namespace App\Http\Requests\Website;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveWebsitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-website');
    }

    /**
     * Page name, search-engine text and the hero banner (mark coloured words in the title with [brackets]).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $link = ['nullable', 'string', 'max:255', 'regex:/^(\/|https?:\/\/|#|mailto:|tel:)/'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'hero_pill' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_text' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*.regex' => 'Links start with / (a page of this site), http(s)://, mailto: or tel:.'];
    }
}
