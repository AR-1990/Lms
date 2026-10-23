<?php

namespace App\Http\Requests\Collaboration;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStickyNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:5000'],
            'color_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'sticky_note_color')->whereNull('deleted_at')],
            'is_pinned' => ['sometimes', 'boolean'],
            'show_on_dashboard' => ['sometimes', 'boolean'],
            'remind_at' => ['nullable', 'date'],
            'items' => ['sometimes', 'array', 'max:30'],
            'items.*.text' => ['required', 'string', 'max:255'],
            'items.*.is_done' => ['sometimes', 'boolean'],
        ];
    }
}
