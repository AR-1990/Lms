<?php

namespace App\Http\Requests\ERP;

use App\Models\NumberSequence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNumberSequenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-numbering');
    }

    /**
     * The counter may only move forward, so numbers already issued are never handed out again.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/_.]*$/'],
            'include_year' => ['required', 'boolean'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'next_number' => ['required', 'integer', 'min:'.$this->sequence()->next_number, 'max:9999999999'],
            'reset_yearly' => ['required', 'boolean'],
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
            'prefix.regex' => 'The prefix may only use letters, numbers, and - / _ .',
            'next_number.min' => 'The next number cannot go below :min — lower numbers may already be issued.',
        ];
    }

    private function sequence(): NumberSequence
    {
        return $this->route('numberSequence');
    }
}
