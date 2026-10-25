<?php

namespace App\Http\Requests\Students;

use App\Models\StudentAchievement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Medical record, discipline incident or achievement of a student (the type comes from the URL).
 */
class SaveStudentRecordRequest extends FormRequest
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
        $lookup = fn (string $type, bool $required = false): array => [$required ? 'required' : 'nullable', 'integer', Rule::exists('lookups', 'id')->where('type', $type)->whereNull('deleted_at')];

        return match ($this->route('type')) {
            'medical' => [
                'condition_id' => $lookup('medical_condition'),
                'allergies' => ['nullable', 'string', 'max:2000'],
                'medications' => ['nullable', 'string', 'max:2000'],
                'doctor_name' => ['nullable', 'string', 'max:255'],
                'doctor_phone' => ['nullable', 'string', 'max:30'],
                'height_cm' => ['nullable', 'numeric', 'between:30,250'],
                'weight_kg' => ['nullable', 'numeric', 'between:2,250'],
                'vision' => ['nullable', 'string', 'max:50'],
                'checked_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'discipline' => [
                'incident_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'category_id' => $lookup('discipline_category', true),
                'description' => ['required', 'string', 'max:2000'],
                'action_taken' => ['nullable', 'string', 'max:2000'],
                'reported_by' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
                'notify_parents' => ['sometimes', 'boolean'],
            ],
            'achievements' => [
                'title' => ['required', 'string', 'max:255'],
                'category_id' => $lookup('achievement_category'),
                'level' => ['nullable', Rule::in(StudentAchievement::LEVELS)],
                'achieved_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'description' => ['nullable', 'string', 'max:2000'],
            ],
            default => [],
        };
    }
}
