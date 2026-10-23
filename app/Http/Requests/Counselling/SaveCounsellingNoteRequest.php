<?php

namespace App\Http\Requests\Counselling;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCounsellingNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One counselling session; confidential (the default) keeps it away from the student and parents.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'session_date' => ['required', 'date', 'before_or_equal:today'],
            'counsellor_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'concern_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'counselling_concern')->whereNull('deleted_at')],
            'performance_alert_id' => ['nullable', 'integer', Rule::exists('performance_alerts', 'id')],
            'notes' => ['required', 'string', 'max:10000'],
            'action_plan' => ['nullable', 'string', 'max:5000'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:session_date'],
            'parents_met' => ['sometimes', 'boolean'],
            'is_confidential' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['student_id' => 'student', 'counsellor_id' => 'counsellor', 'concern_id' => 'concern', 'follow_up_on' => 'follow-up date', 'session_date' => 'session date'];
    }
}
