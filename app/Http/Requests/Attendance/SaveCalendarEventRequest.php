<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-calendar');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'event_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'calendar_event_type')->whereNull('deleted_at')],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
            'is_holiday' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'audience_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
