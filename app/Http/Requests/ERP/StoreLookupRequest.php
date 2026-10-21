<?php

namespace App\Http\Requests\ERP;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLookupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-lookups');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('lookups', 'name')->where('type', (string) $this->input('type'))->whereNull('deleted_at'),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.regex' => 'The list key may only use lowercase letters, numbers and underscores, starting with a letter.',
            'name.unique' => 'This option already exists in the selected list.',
        ];
    }

    /**
     * Normalise free-text input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => is_string($this->input('type')) ? strtolower(trim($this->input('type'))) : $this->input('type'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }
}
