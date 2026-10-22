<?php

namespace App\Http\Requests\Academics;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSchoolClassRequest extends FormRequest
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
        $classId = $this->route('class')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('classes', 'name')->ignore($classId)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('classes', 'code')->ignore($classId)->whereNull('deleted_at')],
            'level_order' => ['required', 'integer', 'min:0', 'max:32000'],
            'wing_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'wing')->whereNull('deleted_at')],
            'next_class_id' => ['nullable', 'integer', Rule::notIn(array_filter([$classId])), Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'next_class_id.not_in' => 'A class cannot promote into itself.',
            'code.regex' => 'Use capital letters, numbers and dashes only.',
        ];
    }
}
