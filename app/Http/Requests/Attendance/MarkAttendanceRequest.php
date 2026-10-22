<?php

namespace App\Http\Requests\Attendance;

use App\Models\StaffAttendance;
use App\Models\StudentAttendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One day's register: students of a section (route attendance.store) or staff (route attendance.staff.store).
 */
class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission($this->isStaff() ? 'manage-staff-attendance' : 'record-attendance');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isStaff()) {
            return [
                'date' => ['required', 'date_format:Y-m-d'],
                'rows' => ['required', 'array', 'min:1', 'max:500'],
                'rows.*.employee_id' => ['required', 'integer', 'distinct', Rule::exists('employees', 'id')->whereNull('deleted_at')],
                'rows.*.status' => ['required', Rule::in(StaffAttendance::STATUSES)],
                'rows.*.check_in_at' => ['nullable', 'date_format:H:i'],
                'rows.*.check_out_at' => ['nullable', 'date_format:H:i'],
                'rows.*.remarks' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d'],
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'rows.*.status' => ['required', Rule::in(StudentAttendance::STATUSES)],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['rows.*.status.required' => 'Mark every student (or staff member) before saving.'];
    }

    private function isStaff(): bool
    {
        return $this->routeIs('erp.attendance.staff.store');
    }
}
