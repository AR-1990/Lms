<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One class of the promotion wizard: undoing its move, or numbering its students in the new session.
 */
class PromotionClassRequest extends FormRequest
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
        $session = ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')];

        return [
            'from_session_id' => $this->routeIs('erp.promotions.undo') ? $session : ['nullable'],
            'to_session_id' => $session,
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'order' => [Rule::requiredIf($this->routeIs('erp.promotions.roll-numbers')), Rule::in(['name', 'admission_no'])],
        ];
    }
}
