<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTimetableSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-timetable');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'period_id' => ['required', 'integer', Rule::exists('periods', 'id')->where('is_break', 0)->whereNull('deleted_at')],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'teacher_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('is_teaching_staff', 1)->whereNull('deleted_at')],
            'room' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['period_id.exists' => 'Choose a teaching period (not a break).'];
    }
}
