<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Cancelling or reversing needs a reason (it becomes the reversal narration).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
