<?php

namespace App\Http\Requests\Academics;

use App\Models\AcademicSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-academics');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30', Rule::unique('academic_sessions', 'name')->ignore($this->route('academic_session'))->whereNull('deleted_at')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
            'admissions_open' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Sessions may not overlap, so every date belongs to exactly one session.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $overlapping = AcademicSession::query()
                ->where('starts_on', '<=', $this->date('ends_on'))
                ->where('ends_on', '>=', $this->date('starts_on'))
                ->when($this->route('academic_session'), fn ($query, AcademicSession $session) => $query->whereKeyNot($session->id))
                ->value('name');

            if ($overlapping !== null) {
                $validator->errors()->add('starts_on', "These dates overlap the {$overlapping} session.");
            }
        });
    }
}
