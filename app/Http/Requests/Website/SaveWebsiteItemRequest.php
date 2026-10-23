<?php

namespace App\Http\Requests\Website;

use App\Models\WebsiteSectionItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWebsiteItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-website');
    }

    /**
     * A card (or, with parent_id, a bullet point of a card) inside a block.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $link = ['nullable', 'string', 'max:255', 'regex:/^(\/|https?:\/\/|#|mailto:|tel:)/'];

        return [
            'parent_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:3000'],
            'icon' => ['nullable', 'string', 'max:40'],
            'tone' => ['nullable', Rule::in(WebsiteSectionItem::TONES)],
            'badge' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:100'],
            'link_label' => ['nullable', 'string', 'max:255'],
            'link_url' => $link,
            'link2_label' => ['nullable', 'string', 'max:255'],
            'link2_url' => $link,
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
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
