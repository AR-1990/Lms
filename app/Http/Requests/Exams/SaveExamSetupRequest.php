<?php

namespace App\Http\Requests\Exams;

use App\Models\PerformanceAlertRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExamSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-exams');
    }

    /**
     * Rules for the setup list in the URL: terms, scales, halls.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route('type')) {
            'terms' => [
                'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
                'name' => ['required', 'string', 'max:100'],
                'weightage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'starts_on' => ['nullable', 'date_format:Y-m-d'],
                'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                'is_final' => ['sometimes', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            ],
            'scales' => [
                'name' => ['required', 'string', 'max:100'],
                'is_default' => ['sometimes', 'boolean'],
                'items' => ['required', 'array', 'min:1', 'max:20'],
                'items.*.grade' => ['required', 'string', 'max:5'],
                'items.*.min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
                'items.*.max_percent' => ['required', 'numeric', 'min:0', 'max:100', 'gte:items.*.min_percent'],
                'items.*.grade_point' => ['nullable', 'numeric', 'min:0', 'max:9.99'],
                'items.*.remark' => ['nullable', 'string', 'max:100'],
            ],
            'halls' => [
                'name' => ['required', 'string', 'max:100'],
                'rows' => ['nullable', 'integer', 'min:1', 'max:100'],
                'columns' => ['nullable', 'integer', 'min:1', 'max:100'],
                'capacity' => ['required', 'integer', 'min:1', 'max:2000'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'skills' => [
                'name' => ['required', 'string', 'max:100'],
                'skill_group_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'skill_group')->whereNull('deleted_at')],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'rules' => [
                'name' => ['required', 'string', 'max:150'],
                'metric' => ['required', Rule::in(PerformanceAlertRule::METRICS)],
                'operator' => ['required', Rule::in(PerformanceAlertRule::OPERATORS)],
                'threshold' => ['required', 'numeric', 'min:0', 'max:1000'],
                'notify_class_teacher' => ['sometimes', 'boolean'],
                'notify_parents' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => abort(404),
        };
    }
}
