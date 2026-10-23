<?php

namespace App\Http\Requests\Transport;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EndTransportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Take a student off transport from a date.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to_date' => ['required', 'date'],
        ];
    }
}
