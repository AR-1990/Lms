<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveJobInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-recruitment');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'round_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'interview_round_type')->whereNull('deleted_at')],
            'scheduled_at' => ['required', 'date'],
            'mode' => ['nullable', 'string', Rule::in(['in_person', 'online', 'phone'])],
            'venue_or_link' => ['nullable', 'string', 'max:255'],
            'status' => [$this->route('job_interview') ? 'sometimes' : 'prohibited', 'string', Rule::in(['scheduled', 'completed', 'no_show', 'cancelled'])],
            'overall_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'recommendation' => ['nullable', 'string', Rule::in(['proceed', 'hold', 'reject'])],
            'summary' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
