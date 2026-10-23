<?php

namespace App\Http\Requests\Website;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveWebsiteSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-website');
    }

    /**
     * Text of a content block; the block itself (and where it sits) is fixed by the page design.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $link = ['nullable', 'string', 'max:255', 'regex:/^(\/|https?:\/\/|#|mailto:|tel:)/'];

        return [
            'subtitle' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'body' => ['nullable', 'string', 'max:5000'],
            'badge' => ['nullable', 'string', 'max:255'],
            'image_caption' => ['nullable', 'string', 'max:255'],
            'image_note' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => $link,
            'cta2_label' => ['nullable', 'string', 'max:255'],
            'cta2_url' => $link,
            'is_active' => ['sometimes', 'boolean'],
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
