<?php

namespace App\Http\Requests\Appraisals;

use App\Services\AppraisalService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Scores (1–5) per criterion, plus the reviewer's summary; submit = true sends it to the employee.
     * The same request carries a self-review (self_score / self_comment).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = AppraisalService::MAX_SCORE;

        return [
            'submit' => ['sometimes', 'boolean'],
            'scores' => ['sometimes', 'array'],
            'scores.*.id' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:1', "max:{$max}"],
            'scores.*.comment' => ['nullable', 'string', 'max:500'],
            'scores.*.self_score' => ['nullable', 'numeric', 'min:1', "max:{$max}"],
            'scores.*.self_comment' => ['nullable', 'string', 'max:500'],
            'rating_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'appraisal_rating')->whereNull('deleted_at')],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'improvements' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
