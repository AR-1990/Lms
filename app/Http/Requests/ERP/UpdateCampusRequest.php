<?php

namespace App\Http\Requests\ERP;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateCampusRequest extends StoreCampusRequest
{
    /**
     * Same rules as creating, but the code may stay the same as this campus's own.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('campuses', 'code')->ignore($this->route('campus'))],
        ];
    }
}
