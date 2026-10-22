<?php

namespace App\Http\Requests\Academics;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-academics');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'to_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
        ];
    }
}
