<?php

namespace App\Http\Requests\Fees;

use App\Models\FeeStructureItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-fee-setup');
    }

    /**
     * Amounts are in paisa. A class has one structure per session; its items are replaced on save.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'class_id' => [
                'required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at'),
                Rule::unique('fee_structures', 'class_id')->where('academic_session_id', $this->input('academic_session_id'))->whereNull('deleted_at')->ignore($this->route('fee_structure')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.fee_head_id' => ['required', 'integer', Rule::exists('fee_heads', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'items.*.charge_on' => ['required', 'string', Rule::in(FeeStructureItem::CHARGE_ON)],
            'items.*.amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'items.*.due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'items.*.due_in_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'items.*.is_mandatory' => ['sometimes', 'boolean'],
            'items.*.applies_to_new_students_only' => ['sometimes', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
            'items.*.months' => ['nullable', 'array', 'max:12'],
            'items.*.months.*' => ['integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_id.unique' => 'This class already has a fee structure for the session. Edit it instead.',
            'items.*.fee_head_id.exists' => 'Choose an active fee head.',
        ];
    }
}
