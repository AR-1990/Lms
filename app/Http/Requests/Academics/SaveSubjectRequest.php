<?php

namespace App\Http\Requests\Academics;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-academics');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code'))) ?: null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subjectId = $this->route('subject')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')->ignore($subjectId)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('subjects', 'code')->ignore($subjectId)->whereNull('deleted_at')],
            'subject_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'subject_type')->whereNull('deleted_at')],
            'has_practical' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use capital letters, numbers and dashes only.',
        ];
    }
}
