<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * employee_id is only used by HR applying on someone's behalf.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'substitute_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
        ];
    }
}
