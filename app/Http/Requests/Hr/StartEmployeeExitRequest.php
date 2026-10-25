<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeExit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartEmployeeExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-hr');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'exit_type' => ['required', 'string', Rule::in(array_keys(EmployeeExit::TYPES))],
            'reason_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'exit_reason')->whereNull('deleted_at')],
            'notice_date' => ['nullable', 'date_format:Y-m-d'],
            'last_working_day' => ['required', 'date_format:Y-m-d', 'after_or_equal:notice_date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
