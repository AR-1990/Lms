<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdmissionInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-admissions');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'mode' => ['nullable', 'string', Rule::in(['in_person', 'online'])],
            'venue_or_link' => ['nullable', 'string', 'max:255'],
            'overall_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'recommendation' => ['nullable', 'string', Rule::in(['admit', 'waitlist', 'reject'])],
            'feedback' => ['nullable', 'string', 'max:3000'],
            'attended' => ['nullable', 'boolean'],
        ];
    }
}
