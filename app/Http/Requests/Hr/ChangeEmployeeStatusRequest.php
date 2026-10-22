<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeEmployeeStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(Employee::STATUSES)],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
