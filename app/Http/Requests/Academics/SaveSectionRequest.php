<?php

namespace App\Http\Requests\Academics;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-academics');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'name' => [
                'required', 'string', 'max:20',
                Rule::unique('sections', 'name')
                    ->where('academic_session_id', $this->input('academic_session_id'))
                    ->where('class_id', $this->input('class_id'))
                    ->ignore($this->route('section'))
                    ->whereNull('deleted_at'),
            ],
            'room' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This class already has a section with this name in the session.',
        ];
    }
}
