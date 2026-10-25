<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueLeavingCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-students');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'issued_on' => ['required', 'date_format:Y-m-d'],
            'conduct_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'conduct')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
