<?php

namespace App\Http\Requests\Cards;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-cards');
    }

    /**
     * Without holder_ids: every active student of the current session (or one section), or every current employee.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $holderTable = $this->input('holder_type') === 'employee' ? 'employees' : 'students';

        return [
            'holder_type' => ['required', Rule::in(['student', 'employee'])],
            'holder_ids' => ['nullable', 'array', 'max:500'],
            'holder_ids.*' => ['integer', 'distinct', Rule::exists($holderTable, 'id')->whereNull('deleted_at')],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'card_template_id' => ['nullable', 'integer', Rule::exists('card_templates', 'id')->where('card_type', $this->input('holder_type'))->where('is_active', 1)->whereNull('deleted_at')],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ];
    }
}
