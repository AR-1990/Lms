<?php

namespace App\Http\Requests\Communication;

use App\Models\MessageTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-announcements');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'channel' => ['required', Rule::in(MessageTemplate::CHANNELS)],
            'event_key' => ['required', Rule::in(array_keys(MessageTemplate::EVENTS))],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
