<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReverseJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('post-vouchers');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:200'],
            'entry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
