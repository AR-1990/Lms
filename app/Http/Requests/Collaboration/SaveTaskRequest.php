<?php

namespace App\Http\Requests\Collaboration;

use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends FormRequest
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
        $user = Rule::exists('users', 'id')->whereNull('deleted_at');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'task_board_id' => ['nullable', 'integer', Rule::exists('task_boards', 'id')->whereNull('deleted_at')],
            'parent_task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->whereNull('deleted_at')],
            'task_status_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'task_priority')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'task_category')->whereNull('deleted_at')],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', Rule::when($this->filled('start_at'), 'after_or_equal:start_at')],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'spent_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'recurrence' => ['nullable', Rule::in(Task::RECURRENCES)],
            'recurrence_until' => ['nullable', 'date'],
            'assignee_ids' => ['sometimes', 'array', 'max:50'],
            'assignee_ids.*' => ['integer', 'distinct', $user],
            'watcher_ids' => ['sometimes', 'array', 'max:50'],
            'watcher_ids.*' => ['integer', 'distinct', $user],
            'checklist' => ['sometimes', 'array', 'max:50'],
            'checklist.*.title' => ['required', 'string', 'max:255'],
            'checklist.*.is_done' => ['sometimes', 'boolean'],
        ];
    }
}
