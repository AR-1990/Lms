<?php

namespace App\Http\Requests\Transport;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Put a student on a stop (moving them if they already ride).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'transport_stop_id' => ['required', 'integer', Rule::exists('transport_stops', 'id')->whereNull('deleted_at')],
            'from_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
