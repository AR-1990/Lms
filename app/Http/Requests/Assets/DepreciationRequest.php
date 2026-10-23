<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The month to preview, post or reverse; reversing needs a reason.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'reason' => [$this->routeIs('*.reverse') ? 'required' : 'nullable', 'string', 'min:10', 'max:500'],
        ];
    }
}
