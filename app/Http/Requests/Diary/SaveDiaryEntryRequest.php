<?php

namespace App\Http\Requests\Diary;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDiaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-diary');
    }

    /**
     * `create_homework` with a subject also creates a gradable homework.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'section_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'diary_date' => [$creating ? 'required' : 'prohibited', 'date_format:Y-m-d', 'before_or_equal:'.now()->addDays(7)->toDateString()],
            'entry_type_id' => ['required', 'integer', Rule::exists('lookups', 'id')->where('type', 'diary_entry_type')->whereNull('deleted_at')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'title' => ['nullable', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:3000'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'create_homework' => ['sometimes', 'boolean'],
            'max_marks' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'allow_submission' => ['sometimes', 'boolean'],
        ];
    }
}
