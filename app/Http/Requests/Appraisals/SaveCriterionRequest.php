<?php

namespace App\Http\Requests\Appraisals;

use App\Models\AppraisalCriterion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Something staff are rated on and its weight in the overall score.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('appraisal_criteria', 'name')->ignore($this->route('appraisal_criterion')?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'weight_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'applies_to' => ['required', Rule::in(AppraisalCriterion::APPLIES_TO)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
