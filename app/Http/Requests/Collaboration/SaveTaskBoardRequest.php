<?php

namespace App\Http\Requests\Collaboration;

use App\Models\TaskBoard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('use-tasks');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at'), Rule::requiredIf($this->input('visibility') === 'department')],
            'visibility' => ['required', Rule::in(TaskBoard::VISIBILITIES)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_archived' => ['sometimes', 'boolean'],
            'members' => ['sometimes', 'array', 'max:200'],
            'members.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'members.*.role' => ['nullable', Rule::in(['editor', 'viewer'])],
        ];
    }
}
