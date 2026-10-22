<?php

namespace App\Http\Requests\Admissions;

use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EnrolApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-admissions') && $this->user()->hasPermission('manage-students');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admission_date' => ['nullable', 'date_format:Y-m-d'],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'roll_no' => ['nullable', 'string', 'max:20'],
            'parent_password' => ['nullable', 'string', 'min:8'],
        ];
    }

    /**
     * The section must be of the class and session applied for.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $application = $this->route('admission_application');

            if (! $this->filled('section_id') || $application === null) {
                return;
            }

            $fits = Section::whereKey($this->integer('section_id'))
                ->where('class_id', $application->class_applied_id)
                ->where('academic_session_id', $application->academic_session_id)
                ->exists();

            if (! $fits) {
                $validator->errors()->add('section_id', 'Choose a section of the class and session applied for.');
            }
        });
    }
}
