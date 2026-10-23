<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An expense category and the account its expenses are posted to.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->whereNull('deleted_at'), Rule::notIn([$this->route('expense_category')?->id])],
            'expense_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_group', false)->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
