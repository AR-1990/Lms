<?php

namespace App\Http\Requests\Appraisals;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A review period; leave the department empty to appraise all staff.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'self_review' => ['sometimes', 'boolean'],
        ];
    }
}
