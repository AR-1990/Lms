<?php

namespace App\Http\Requests\Library;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A catalogue title; `copies` adds that many new copies (each with its accession number).
 */
class SaveLibraryBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-library');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'book_category')->whereNull('deleted_at')],
            'shelf' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'copies' => [$this->isMethod('post') ? 'required' : 'nullable', 'integer', 'min:'.($this->isMethod('post') ? 1 : 0), 'max:200'],
        ];
    }
}
