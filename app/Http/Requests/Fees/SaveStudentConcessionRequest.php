<?php

namespace App\Http\Requests\Fees;

use App\Models\ConcessionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveStudentConcessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-fee-setup');
    }

    /**
     * Value and calculation default to the concession type's.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'concession_type_id' => ['required', 'integer', Rule::exists('concession_types', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'fee_head_id' => ['nullable', 'integer', Rule::exists('fee_heads', 'id')->whereNull('deleted_at')],
            'calculation' => ['nullable', 'string', Rule::in(['percent', 'fixed'])],
            'value' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $calculation = $this->input('calculation') ?? ConcessionType::whereKey($this->integer('concession_type_id'))->value('calculation');

            if ($calculation === 'percent' && (int) $this->input('value') > 10000) {
                $validator->errors()->add('value', 'A percentage cannot be more than 100%.');
            }
        });
    }
}
