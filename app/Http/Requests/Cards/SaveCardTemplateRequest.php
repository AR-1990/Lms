<?php

namespace App\Http\Requests\Cards;

use App\Models\CardTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCardTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-cards');
    }

    /**
     * Sizes and positions are millimetres; fields[] replaces the whole layout when sent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $type = $this->route('card_template')?->is_system ? $this->route('card_template')->card_type : $this->input('card_type');

        return [
            'name' => ['required', 'string', 'max:150'],
            'card_type' => ['required', 'string', Rule::in(CardTemplate::TYPES), Rule::when($this->route('card_template')?->is_system, ['in:'.$type])],
            'size_key' => ['required', 'string', Rule::in(CardTemplate::SIZES_FOR_TYPE[$type] ?? [])],
            'theme' => ['sometimes', 'string', Rule::in(array_keys(CardTemplate::THEMES))],
            'primary_color' => $hex,
            'text_color' => $hex,
            'show_qr' => ['sometimes', 'boolean'],
            'back_text' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'array', 'max:40'],
            'fields.*.side' => ['required', Rule::in(['front', 'back'])],
            'fields.*.field_key' => ['required', Rule::in(CardTemplate::KEYS_FOR_TYPE[$type] ?? [])],
            'fields.*.custom_text' => ['nullable', 'required_if:fields.*.field_key,custom_text', 'string', 'max:255'],
            'fields.*.x_mm' => ['required', 'numeric', 'min:0', 'max:300'],
            'fields.*.y_mm' => ['required', 'numeric', 'min:0', 'max:300'],
            'fields.*.width_mm' => ['nullable', 'numeric', 'min:1', 'max:300'],
            'fields.*.height_mm' => ['nullable', 'numeric', 'min:1', 'max:300'],
            'fields.*.font_size' => ['nullable', 'numeric', 'min:4', 'max:40'],
            'fields.*.font_weight' => ['nullable', Rule::in(['normal', 'bold'])],
            'fields.*.color' => $hex,
            'fields.*.align' => ['nullable', Rule::in(['left', 'center', 'right'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'size_key.in' => 'Choose one of the sizes allowed for this card type.',
            'card_type.in' => 'A system template keeps its card type.',
            'fields.*.field_key.in' => 'This item cannot be placed on this kind of card.',
        ];
    }
}
