<?php

namespace App\Http\Requests\Accounts;

use App\Models\FiscalYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-accounts');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30', Rule::unique('fiscal_years', 'name')->ignore($this->route('fiscal_year'))],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Years may not overlap, so every voucher date belongs to exactly one year.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $overlapping = FiscalYear::query()
                ->whereDate('starts_on', '<=', $this->input('ends_on'))
                ->whereDate('ends_on', '>=', $this->input('starts_on'))
                ->when($this->route('fiscal_year'), fn ($query, FiscalYear $year) => $query->whereKeyNot($year->id))
                ->value('name');

            if ($overlapping !== null) {
                $validator->errors()->add('starts_on', "These dates overlap {$overlapping}.");
            }
        });
    }
}
