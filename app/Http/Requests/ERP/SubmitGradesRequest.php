<?php

namespace App\Http\Requests\ERP;

use Illuminate\Foundation\Http\FormRequest;

class SubmitGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required'],
            'assessment_title' => ['required', 'string'],
            'grades' => ['required', 'array'],
        ];
    }
}
